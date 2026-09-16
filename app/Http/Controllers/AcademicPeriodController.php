<?php

namespace App\Http\Controllers;

use App\Actions\SetUpPeriods;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Term;
use App\Notifications\ExamEntryOpened;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Six marking periods, two semesters, and the switch for exam marks.
 *
 * Three separate authorities, because a school hands them to different people:
 *
 *   academics.view     see the calendar
 *   academics.manage   set it up and change period and exam dates
 *   grades.exam_entry  open and close semester exam mark entry
 *
 * Opening exam entry is the administration saying "the exam has been sat, the
 * teachers may now record it", and it is audited with the name of whoever said
 * it. Closing it again stops further entry without touching anything already
 * recorded.
 */
class AcademicPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->hasAnyPermission(['academics.view', 'academics.manage', 'grades.exam_entry']), 403);

        $years = AcademicYear::orderByDesc('starts_on')->get();
        $year = $request->integer('year')
            ? $years->firstWhere('id', $request->integer('year'))
            : AcademicYear::active();

        abort_if($request->integer('year') && $year === null, 404);

        $periods = $year
            ? Term::where('academic_year_id', $year->id)->whereNotNull('semester')->orderBy('sequence')->get()
            : collect();

        return view('periods.index', [
            'years' => $years,
            'year' => $year,
            'periods' => $periods,
            'plainTerms' => $year ? Term::where('academic_year_id', $year->id)->whereNull('semester')->orderBy('sequence')->get() : collect(),
            'semesters' => $year ? $year->semesters()->with('openedBy:id,name')->get() : collect(),
            'canManage' => $user->hasPermission('academics.manage'),
            'canOpenExams' => $user->hasPermission('grades.exam_entry'),
        ]);
    }

    /** Organise a year as six periods in two semesters (see SetUpPeriods). */
    public function setup(Request $request, AcademicYear $academicYear, SetUpPeriods $action, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        if ($academicYear->usesPeriods() && Term::where('academic_year_id', $academicYear->id)->whereNotNull('semester')->count() === Term::PERIODS_PER_YEAR) {
            return back()->with('status', "{$academicYear->name} is already organised into periods.");
        }

        if ($problem = $action->handle($academicYear)) {
            return back()->withErrors(['periods' => $problem]);
        }

        $audit->log('updated', 'Academics', "{$academicYear->name} was organised into six periods and two semesters.");

        return back()->with('status', "{$academicYear->name} now has six periods in two semesters. Set each period's dates below.");
    }

    public function updatePeriod(Request $request, Term $term, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);
        abort_unless($term->isPeriod(), 404);

        $year = $term->academicYear;

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ], [], ['starts_on' => 'start date', 'ends_on' => 'end date']);

        if ($data['starts_on'] < $year->starts_on->toDateString() || $data['ends_on'] > $year->ends_on->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => "A period must fall inside {$year->name} ({$year->starts_on->format('j M Y')} to {$year->ends_on->format('j M Y')})."]);
        }

        $overlap = Term::where('academic_year_id', $year->id)
            ->whereKeyNot($term->id)
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => "These dates overlap {$overlap->name} ({$overlap->starts_on->format('j M Y')} to {$overlap->ends_on->format('j M Y')})."]);
        }

        $original = $term->only(['starts_on', 'ends_on']);
        $term->update($data);

        $audit->log('updated', 'Academics', "The dates of {$term->name} were changed.", $term, $original, $data);

        return back()->with('status', "{$term->name} updated.");
    }

    public function updateSemester(Request $request, Semester $semester, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        $data = $request->validate([
            'exam_starts_on' => ['nullable', 'date'],
            'exam_ends_on' => ['nullable', 'date', 'after_or_equal:exam_starts_on'],
        ], [], ['exam_starts_on' => 'exam start date', 'exam_ends_on' => 'exam end date']);

        $semester->update($data);

        $audit->log('updated', 'Academics', "The {$semester->name} examination dates were changed.", $semester);

        return back()->with('status', "{$semester->name} exam dates saved.");
    }

    /** Open or close semester exam mark entry for teachers. */
    public function examEntry(Request $request, Semester $semester, AuditLogger $audit, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('grades.exam_entry'), 403);

        $open = $request->boolean('open');

        if ($open === $semester->exam_entry_open) {
            return back()->with('status', 'No change.');
        }

        $semester->update([
            'exam_entry_open' => $open,
            'exam_entry_opened_at' => $open ? now() : $semester->exam_entry_opened_at,
            'exam_entry_opened_by' => $open ? $request->user()->id : $semester->exam_entry_opened_by,
        ]);

        $audit->log($open ? 'exam_entry_opened' : 'exam_entry_closed', 'Examinations & grades',
            "{$semester->name} exam mark entry was ".($open ? 'opened' : 'closed').' by '.$request->user()->name.'.', $semester);

        if ($open) {
            $notifier->notifyPermission('grades.enter', new ExamEntryOpened($semester));
        }

        return back()->with('status', $open
            ? "{$semester->name} exam entry is open. Teachers have been told."
            : "{$semester->name} exam entry is closed. Marks already entered are kept.");
    }
}
