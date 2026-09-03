<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Services\Gradebook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The whole school's results in one table (spec sections 37-38).
 *
 * Everything here shows **approved marks only**, through `Gradebook`. A mark a
 * teacher has entered but nobody has signed off is not a result, and an
 * administrator seeing it in a results table would have no way of telling the
 * difference.
 *
 * An administrator may correct an approved mark, which a teacher cannot. That
 * asymmetry is deliberate and is the reason the correction screen exists at all:
 * once marks are approved the teacher's route to changing them is to have the
 * assessment rejected and re-entered, while the academic office can fix a
 * transcription error directly. Every such correction is written to the audit
 * trail with the old and new value, because a grade changed after approval is
 * exactly the kind of thing a parent may one day ask about.
 */
class GradebookController extends Controller
{
    public function index(Request $request, Gradebook $gradebook): View
    {
        abort_unless($request->user()->hasPermission('reportcards.view'), 403);

        $year = AcademicYear::active();
        $terms = Term::when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('sequence')->get();

        $term = $this->resolveTerm($request, $terms);

        $sections = Section::with('schoolClass')->get()
            ->sortBy(fn (Section $s) => $s->full_name)->values();

        $section = $request->integer('section')
            ? $sections->firstWhere('id', $request->integer('section'))
            : $sections->first();

        return view('gradebook.index', [
            'year' => $year,
            'terms' => $terms,
            'term' => $term,
            'sections' => $sections,
            'section' => $section,
            'subjects' => $section && $term ? $gradebook->subjectsTaught($section->id, $term) : collect(),
            'rows' => $section && $term ? $gradebook->sectionResults($section->id, $term) : collect(),
            'passMark' => $gradebook->passMark(),
            'scaleDisagrees' => $gradebook->passMarkDisagreesWithScale(),
            // Shown so nobody reads an empty table as "no marks exist".
            'awaiting' => $section && $term
                ? Assessment::where('section_id', $section->id)
                    ->where('term_id', $term->id)
                    ->whereIn('status', ['draft', 'submitted'])
                    ->count()
                : 0,
        ]);
    }

    /** One student's full record for a term, with every mark behind the average. */
    public function show(Request $request, Student $student, Gradebook $gradebook): View
    {
        abort_unless($request->user()->hasPermission('reportcards.view'), 403);
        abort_unless($student->school_id === $request->user()->school_id, 403);

        $year = AcademicYear::active();
        $terms = Term::when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('sequence')->get();

        $term = $this->resolveTerm($request, $terms);

        return view('gradebook.student', [
            'student' => $student->load('currentEnrollment.section.schoolClass'),
            'terms' => $terms,
            'term' => $term,
            'results' => $term ? $gradebook->termResults($student, $term) : null,
            'progress' => $gradebook->yearResults($student, $terms),
            'passMark' => $gradebook->passMark(),
            'canEdit' => $request->user()->hasPermission('grades.approve'),
        ]);
    }

    /**
     * Correct a single approved mark.
     *
     * Gated on `grades.approve` rather than `grades.enter`: the ability to
     * change a mark after it has been signed off belongs with the office that
     * signed it off, not with everyone who can record one.
     */
    public function updateScore(Request $request, AssessmentScore $score, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('grades.approve'), 403);
        abort_unless($score->school_id === $request->user()->school_id, 403);

        $score->load('assessment', 'student');

        $assessment = $score->assessment;

        abort_unless($assessment !== null, 404);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assessment->max_score],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'score.max' => "A mark cannot be higher than the maximum of {$assessment->max_score}.",
            'reason.required' => 'Say why this mark is being changed. It is kept with the change.',
        ], ['reason' => 'reason for the change']);

        $before = (float) $score->score;

        $score->update(['score' => $data['score'], 'recorded_by' => $request->user()->id]);

        /*
         | The reason is required and stored, not optional. "Who changed this
         | grade, when, from what, and why" is the whole point of recording a
         | correction; without the why an audit entry only proves that somebody
         | did it.
         */
        $audit->log(
            'updated',
            'Examinations & grades',
            "{$score->student?->full_name}'s mark for \"{$assessment->title}\" was corrected "
                ."from {$before} to {$data['score']}. Reason: {$data['reason']}",
            $score,
            ['score' => $before],
            ['score' => (float) $data['score'], 'reason' => $data['reason']],
        );

        return back()->with('status', 'Mark corrected. The change is recorded in the audit trail.');
    }

    /** Remove a mark entirely, where it was recorded against the wrong child. */
    public function destroyScore(Request $request, AssessmentScore $score, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('grades.approve'), 403);
        abort_unless($score->school_id === $request->user()->school_id, 403);

        $score->load('assessment', 'student');

        $description = "{$score->student?->full_name}'s mark of {$score->score} for "
            ."\"{$score->assessment?->title}\" was removed.";

        DB::transaction(fn () => $score->delete());

        $audit->log('deleted', 'Examinations & grades', $description, null);

        return back()->with('status', 'Mark removed. The removal is recorded in the audit trail.');
    }

    /** The term being viewed: the one asked for, else the current one. */
    protected function resolveTerm(Request $request, $terms): ?Term
    {
        if ($request->integer('term')) {
            return $terms->firstWhere('id', $request->integer('term')) ?? Term::active();
        }

        return $terms->firstWhere('is_current', true) ?? $terms->first();
    }
}
