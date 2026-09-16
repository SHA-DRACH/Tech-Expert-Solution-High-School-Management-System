<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\PeriodConduct;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Services\DocumentCode;
use App\Services\Gradebook;
use App\Services\PeriodGrades;
use App\Services\ProgressReport;
use App\Services\StudentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The paper a family receives: a grade sheet every period, and a report card
 * (the periodic progress report) at the end of the year.
 *
 * Both are drawn from approved marks only, for a whole class at once so that
 * rank and class size mean the same thing on every child's paper. The same
 * two documents serve staff (a class to print), a student (their own) and a
 * parent (a child they are linked to and cleared for).
 */
class ProgressReportController extends Controller
{
    public function __construct(
        private readonly ProgressReport $reports,
        private readonly PeriodGrades $grades,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Staff                                                               */
    /* ------------------------------------------------------------------ */

    /** Choose a class and a period; see its students; record conduct. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reportcards.view'), 403);

        $year = AcademicYear::active();
        $periods = $year ? $this->grades->periods($year) : collect();
        $sections = Section::with('schoolClass', 'classTeacher')->get()->sortBy(fn (Section $s) => $s->full_name)->values();

        $section = $request->integer('section')
            ? $sections->firstWhere('id', $request->integer('section'))
            : $sections->first();

        $period = $request->integer('period')
            ? $periods->firstWhere('id', $request->integer('period'))
            : ($year ? $this->grades->currentPeriod($year) : null);

        $report = $section && $year && $periods->isNotEmpty() ? $this->reports->forClass($section, $year) : null;

        return view('progress.index', [
            'year' => $year,
            'periods' => $periods,
            'sections' => $sections,
            'section' => $section,
            'period' => $period,
            'periodNumber' => $period?->periodNumber(),
            'report' => $report,
            'canRecordConduct' => $section && $this->canRecordConduct($request, $section),
            'conductOptions' => PeriodConduct::OPTIONS,
        ]);
    }

    public function classGradeSheets(Request $request, Section $section): View
    {
        abort_unless($request->user()->hasPermission('reportcards.view'), 403);

        return $this->gradeSheetDocument($request, $section, $this->onlyStudent($request));
    }

    public function classReportCards(Request $request, Section $section): View
    {
        abort_unless($request->user()->hasPermission('reportcards.view'), 403);

        return $this->reportCardDocument($request, $section, $this->onlyStudent($request));
    }

    /**
     * Conduct for a period, written by the class sponsor.
     *
     * The sponsor, not every subject teacher: conduct on a grade sheet is one
     * judgement about the child across the period, and the sponsor signs it.
     * The academic office may also record it.
     */
    public function storeConduct(Request $request, Section $section, AuditLogger $audit): RedirectResponse
    {
        abort_unless($this->canRecordConduct($request, $section), 403);

        $data = $request->validate([
            'period' => ['required', 'integer'],
            'conduct' => ['array'],
            'conduct.*' => ['nullable', Rule::in(PeriodConduct::OPTIONS)],
        ]);

        $period = Term::whereNotNull('semester')->findOrFail($data['period']);
        $students = Student::inSection($section->id)->pluck('id');
        $saved = 0;

        foreach ($data['conduct'] ?? [] as $studentId => $conduct) {
            // Only a child actually in this class; any other id is ignored.
            if (! $students->contains((int) $studentId)) {
                continue;
            }

            if (blank($conduct)) {
                PeriodConduct::where('student_id', $studentId)->where('term_id', $period->id)->delete();

                continue;
            }

            PeriodConduct::updateOrCreate(
                ['student_id' => (int) $studentId, 'term_id' => $period->id],
                ['school_id' => $section->school_id, 'conduct' => $conduct, 'recorded_by' => $request->user()->id],
            );

            $saved++;
        }

        $audit->log('updated', 'Examinations & grades',
            "Conduct was recorded for {$section->full_name}, {$period->label()} ({$saved} students).");

        return back()->with('status', 'Conduct saved.');
    }

    /* ------------------------------------------------------------------ */
    /* Students and parents                                               */
    /* ------------------------------------------------------------------ */

    public function studentGradeSheet(Request $request): View
    {
        $student = $this->ownStudent($request);

        return $this->gradeSheetDocument($request, $this->sectionOf($student), $student);
    }

    public function studentReportCard(Request $request): View
    {
        $student = $this->ownStudent($request);

        return $this->reportCardDocument($request, $this->sectionOf($student), $student);
    }

    public function parentGradeSheet(Request $request): View
    {
        $child = $this->linkedChild($request);

        return $this->gradeSheetDocument($request, $this->sectionOf($child), $child);
    }

    public function parentReportCard(Request $request): View
    {
        $child = $this->linkedChild($request);

        return $this->reportCardDocument($request, $this->sectionOf($child), $child);
    }

    /* ------------------------------------------------------------------ */

    protected function gradeSheetDocument(Request $request, Section $section, ?Student $only): View
    {
        $year = AcademicYear::active();
        abort_unless($year !== null, 404, 'There is no current academic year.');

        $periods = $this->grades->periods($year);
        abort_if($periods->isEmpty(), 404, 'This year is not set up in periods yet.');

        $period = $request->integer('period')
            ? $periods->firstWhere('id', $request->integer('period'))
            : $this->grades->currentPeriod($year);
        abort_unless($period !== null, 404);

        $number = $period->periodNumber();
        $semester = (int) $period->semester;

        // The semester's periods so far; the exam and average join them on
        // the grade sheet for the last period of the semester.
        $columns = $periods->filter(fn (Term $p, int $n) => (int) $p->semester === $semester && $n <= $number)
            ->keys()
            ->map(fn (int $n) => 'p'.$n)
            ->values()
            ->all();

        if ($number % 3 === 0) {
            array_push($columns, 'e'.$semester, 's'.$semester);
        }

        $report = $this->reports->forClass($section, $year);

        return view('progress.grade-sheet', [
            'school' => $request->user()->school,
            'report' => $report,
            'rows' => $this->rowsFor($report, $only),
            'period' => $period,
            'periodNumber' => $number,
            'semesterNumber' => $semester,
            'columns' => $columns,
            'codes' => $this->codes($report, $only, 'grade-sheet', (string) $period->id),
            'backUrl' => url()->previous(),
        ]);
    }

    protected function reportCardDocument(Request $request, Section $section, ?Student $only): View
    {
        $year = AcademicYear::active();
        abort_unless($year !== null, 404, 'There is no current academic year.');
        abort_if($this->grades->periods($year)->isEmpty(), 404, 'This year is not set up in periods yet.');

        $report = $this->reports->forClass($section, $year);

        return view('progress.report-card', [
            'school' => $request->user()->school,
            'report' => $report,
            'rows' => $this->rowsFor($report, $only),
            'passMark' => app(Gradebook::class)->passMark(),
            'note' => app(\App\Services\SchoolSettings::class)->get('reportcard_remark'),
            'codes' => $this->codes($report, $only, 'progress-report', (string) $year->id),
            'backUrl' => url()->previous(),
        ]);
    }

    protected function rowsFor(array $report, ?Student $only): Collection
    {
        if ($only === null) {
            return $report['students']->values();
        }

        $row = $report['students']->get($only->id);
        abort_unless($row !== null, 404);

        return collect([$row]);
    }

    /** @return array<int, ?string> QR codes keyed by student id */
    protected function codes(array $report, ?Student $only, string $type, string $suffix): array
    {
        $documentCode = app(DocumentCode::class);

        return $report['students']
            ->when($only, fn ($rows) => $rows->only([$only->id]))
            ->mapWithKeys(fn (array $row) => [
                $row['student']->id => $documentCode->forDocument($type, $row['student']->student_number.'.'.$suffix, 90),
            ])
            ->all();
    }

    protected function onlyStudent(Request $request): ?Student
    {
        return $request->integer('student') ? Student::findOrFail($request->integer('student')) : null;
    }

    protected function sectionOf(Student $student): Section
    {
        $section = $student->currentEnrollment?->section;
        abort_unless($section !== null, 404, 'Not placed in a class this year.');

        return $section;
    }

    protected function ownStudent(Request $request): Student
    {
        $student = $request->user()->studentProfile()->with('currentEnrollment.section.schoolClass')->first();
        abort_unless($student !== null, 403);

        abort_unless(app(StudentAccess::class)->allows($student, 'view_report_cards'), 403,
            'Your school has not enabled report cards on your account.');

        return $student;
    }

    protected function linkedChild(Request $request): Student
    {
        $guardian = $request->user()->guardianProfile()->first();
        abort_unless($guardian !== null, 403);

        $children = $guardian->students()->with('currentEnrollment.section.schoolClass')->get();
        $child = $children->firstWhere('id', $request->integer('child')) ?? $children->first();

        abort_unless($child !== null, 404);
        abort_unless($guardian->canViewAcademicsFor($child), 403, 'You are not cleared to view academic records for this child.');

        return $child;
    }

    protected function canRecordConduct(Request $request, Section $section): bool
    {
        $user = $request->user();

        if ($user->hasPermission('grades.approve')) {
            return true;
        }

        $section->loadMissing('classTeacher');

        return $user->hasPermission('grades.enter')
            && $section->classTeacher !== null
            && $section->classTeacher->user_id === $user->id;
    }
}
