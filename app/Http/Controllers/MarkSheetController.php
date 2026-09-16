<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMarkingScope;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Services\AuditLogger;
use App\Services\Gradebook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The mark sheet: one class, one subject, every student, every assessment.
 *
 * The scores screen already existed but was arranged the other way round - you
 * opened one assessment and marked the class against it. A teacher thinks in
 * terms of "Grade 9A, Mathematics", wants to see the whole picture for that
 * pairing, and wants to put a single mark against a single child without
 * hunting for which assessment it belonged to. This is that view.
 *
 * Who may change what is decided in one place, `editableAssessments()`:
 *
 * - A teacher may enter marks for a subject they are assigned to, and only
 *   while the assessment is still a draft or has been sent back. Once it is
 *   submitted it is out of their hands - that is the whole point of approval.
 * - Someone holding `grades.approve` may correct any mark, including an
 *   approved one, because after sign-off the academic office is the only route
 *   left to fixing a transcription error. Those corrections are audited.
 */
class MarkSheetController extends Controller
{
    use ResolvesMarkingScope;

    public function index(Request $request, Gradebook $gradebook): View
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('grades.enter') || $user->hasPermission('grades.approve'),
            403,
        );

        $year = AcademicYear::active();

        $terms = Term::when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('sequence')
            ->get();

        $term = $request->integer('term')
            ? $terms->firstWhere('id', $request->integer('term'))
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        // A teacher sees the classes they teach; the academic office sees all.
        $teacher = $user->teacherProfile;
        // Restricted unless they hold office-wide authority. Having no teacher
        // record narrows an account to nothing - it must never widen it to all.
        $restricted = ! $user->hasPermission('grades.approve');

        $sections = $this->sectionsFor($teacher, $restricted);
        $section = $request->integer('section')
            ? $sections->firstWhere('id', $request->integer('section'))
            : $sections->first();

        $subjects = $section ? $this->subjectsFor($section, $teacher, $restricted) : collect();
        $subject = $request->integer('subject')
            ? $subjects->firstWhere('id', $request->integer('subject'))
            : $subjects->first();

        $assessments = $section && $subject && $term
            ? Assessment::where('section_id', $section->id)
                ->where('subject_id', $subject->id)
                ->where('term_id', $term->id)
                ->orderBy('starts_at')
                ->orderBy('id')
                ->get()
            : collect();

        $students = $section && $subject ? $this->roster($section, $subject) : collect();

        return view('marks.index', [
            'terms' => $terms,
            'term' => $term,
            'sections' => $sections,
            'section' => $section,
            'subjects' => $subjects,
            'subject' => $subject,
            'assessments' => $assessments,
            'students' => $students,
            'scores' => $this->scoreMap($assessments, $students),
            'editable' => $this->editableAssessments($request, $assessments),
            'passMark' => $gradebook->passMark(),
            'canApprove' => $user->hasPermission('grades.approve'),
            /*
             | Said plainly on the page. A class with no subjects attached looks
             | identical to one whose teacher has not been assigned, and the two
             | are fixed in completely different places.
             */
            'subjectsUnavailable' => $section !== null && $subjects->isEmpty(),
        ]);
    }

    /**
     * Save the sheet.
     *
     * Accepts the whole grid or a single cell - the individual-entry form posts
     * exactly the same shape with one entry in it, so there is one save path to
     * get right rather than two.
     */
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('grades.enter') || $user->hasPermission('grades.approve'),
            403,
        );

        $request->validate([
            'marks' => ['required', 'array'],
            'marks.*' => ['array'],
            'marks.*.*' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $submitted = collect($request->input('marks'))
            ->flatMap(fn (array $byStudent, $assessmentId) => collect($byStudent)
                ->map(fn ($mark, $studentId) => [
                    'assessment_id' => (int) $assessmentId,
                    'student_id' => (int) $studentId,
                    'mark' => $mark,
                ])
                ->values());

        if ($submitted->isEmpty()) {
            return back()->with('status', 'Nothing to save.');
        }

        // Re-resolved through the tenant-scoped query, then re-checked against
        // the same rules the screen used to decide what to show as editable.
        $assessments = Assessment::whereIn('id', $submitted->pluck('assessment_id')->unique())->get();
        $editable = $this->editableAssessments($request, $assessments);

        $saved = 0;
        $cleared = 0;
        $corrections = [];

        DB::transaction(function () use ($submitted, $assessments, $editable, $user, &$saved, &$cleared, &$corrections) {
            foreach ($submitted as $entry) {
                $assessment = $assessments->firstWhere('id', $entry['assessment_id']);

                // Silently skipping would tell the user their mark saved when
                // it did not, so anything not permitted stops the request.
                abort_unless($assessment !== null && $editable->contains($assessment->id), 403);

                // Only a student actually in this assessment's class may be
                // marked against it.
                $student = Student::inSection($assessment->section_id)->find($entry['student_id']);

                if ($student === null) {
                    continue;
                }

                $mark = $entry['mark'];

                if ($mark === null || $mark === '') {
                    // Blank means "no mark recorded", which is not the same as
                    // zero and must not be stored as one.
                    $cleared += AssessmentScore::where('assessment_id', $assessment->id)
                        ->where('student_id', $student->id)
                        ->delete();

                    continue;
                }

                abort_if((float) $mark > (float) $assessment->max_score, 422);

                $existing = AssessmentScore::where('assessment_id', $assessment->id)
                    ->where('student_id', $student->id)
                    ->first();

                // A change to an already-approved mark is a correction, and is
                // named as one in the audit trail.
                if ($assessment->status === 'approved' && $existing && (float) $existing->score !== (float) $mark) {
                    $corrections[] = "{$student->full_name}: {$existing->score} → {$mark} for \"{$assessment->title}\"";
                }

                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                    ['school_id' => $assessment->school_id, 'score' => $mark, 'recorded_by' => $user->id],
                );

                $saved++;
            }
        });

        if ($corrections !== []) {
            $audit->log(
                'updated',
                'Examinations & grades',
                'Approved marks were corrected: '.implode('; ', $corrections)
                    .($request->filled('reason') ? '. Reason: '.$request->input('reason') : ''),
                null,
            );
        }

        return back()->with('status', trim(
            ($saved > 0 ? "{$saved} ".\Illuminate\Support\Str::plural('mark', $saved).' saved. ' : '')
            .($cleared > 0 ? "{$cleared} ".\Illuminate\Support\Str::plural('mark', $cleared).' cleared.' : '')
        ) ?: 'No changes.');
    }

    /* ------------------------------------------------------------ helpers */

    /** score keyed as "assessmentId.studentId", for a flat lookup in the view. */
    protected function scoreMap(Collection $assessments, $students): Collection
    {
        if ($assessments->isEmpty() || $students->isEmpty()) {
            return collect();
        }

        return AssessmentScore::whereIn('assessment_id', $assessments->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy(fn (AssessmentScore $score) => $score->assessment_id.'.'.$score->student_id);
    }

    /**
     * Which of these assessments this person may write to.
     *
     * The single place that question is answered, so the screen never offers an
     * input the save would refuse.
     *
     * @return Collection<int, int> assessment ids
     */
    protected function editableAssessments(Request $request, Collection $assessments): Collection
    {
        $user = $request->user();

        return $assessments->filter(function (Assessment $assessment) use ($user) {
            // The academic office may correct anything, including after
            // approval - that is the only route left once marks are signed off.
            if ($user->can('approve', $assessment) || $user->hasPermission('grades.approve')) {
                return true;
            }

            return $user->can('enterScores', $assessment);
        })->pluck('id');
    }
}
