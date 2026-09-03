<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssignmentSubmission;
use App\Models\Examination;
use App\Models\GradeScale;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Notifications\GradesAwaitingApproval;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teacher-facing grade entry (spec section 36).
 *
 * A teacher sets a piece of work, enters a mark for each student, and submits
 * it. Submission hands it to the academic office; nothing reaches a parent or a
 * report card until it is approved there.
 */
class AssessmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Assessment::class);

        $teacher = $this->teacherFor($request);

        $filters = [
            'status' => $request->string('status')->trim()->toString(),
            'section' => $request->integer('section') ?: null,
        ];

        $assessments = Assessment::query()
            ->with(['subject:id,name', 'section.schoolClass', 'teacher:id,first_name,last_name'])
            ->withCount('scores')
            // A teacher sees their own work; the academic office sees all of it.
            ->when($teacher && ! $request->user()->hasPermission('grades.approve'),
                fn ($query) => $query->where('teacher_id', $teacher->id))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['section'], fn ($query, $id) => $query->where('section_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('assessments.index', [
            'assessments' => $assessments,
            'filters' => $filters,
            'statuses' => Assessment::STATUSES,
            'sections' => $this->sectionsFor($request, $teacher),
            'canApprove' => $request->user()->hasPermission('grades.approve'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Assessment::class);

        $teacher = $this->teacherFor($request);

        return view('assessments.create', [
            'sections' => $this->sectionsFor($request, $teacher),
            'subjects' => $this->subjectsFor($request, $teacher),
            'examinations' => Examination::orderByDesc('starts_on')->get(),
            'types' => Assessment::TYPES,
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Assessment::class);

        $data = $this->validateAssessment($request);

        $teacher = $this->teacherFor($request);
        $section = Section::findOrFail($data['section_id']);
        $subject = Subject::findOrFail($data['subject_id']);

        // A teacher may only set work for a section and subject they teach.
        if ($teacher && ! $request->user()->hasPermission('exams.manage')) {
            abort_unless(
                TeachingAssignment::where('teacher_id', $teacher->id)
                    ->where('section_id', $section->id)
                    ->where('subject_id', $subject->id)
                    ->exists(),
                403,
                'You are not assigned to teach that subject to that class.'
            );
        }

        $year = AcademicYear::active();

        abort_unless($year !== null, 422, 'Set up an academic year before recording assessments.');

        $assessment = Assessment::create([
            'academic_year_id' => $year->id,
            'term_id' => $data['term_id'] ?? Term::active()?->id,
            'examination_id' => $data['examination_id'] ?? null,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher?->id,
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'type' => $data['type'],
            'max_score' => $data['max_score'],
            'weight' => $data['weight'] ?? 1,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => 'draft',
        ] + $this->questionPaper($request));

        $audit->log('created', 'Examinations & grades',
            "Assessment \"{$assessment->title}\" was created for {$section->full_name}.", $assessment);

        return redirect()
            ->route('assessments.scores', $assessment)
            ->with('status', 'Assessment created. Enter the marks below.');
    }

    public function edit(Assessment $assessment): View
    {
        $this->authorize('update', $assessment);

        return view('assessments.edit', [
            'assessment' => $assessment,
            'types' => Assessment::TYPES,
            'examinations' => Examination::orderByDesc('starts_on')->get(),
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }

    public function update(Request $request, Assessment $assessment, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $assessment);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Assessment::TYPES)],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:8000'],
            'question_paper' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:8192'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'term_id' => ['nullable', 'integer'],
            'examination_id' => ['nullable', 'integer'],
        ]);

        // The uploaded file itself is not an attribute; questionPaper() turns it
        // into the stored path and name.
        $assessment->update(
            collect($data)->except('question_paper')->all() + $this->questionPaper($request, $assessment)
        );

        $audit->log('updated', 'Examinations & grades',
            "Assessment \"{$assessment->title}\" was updated.", $assessment);

        return redirect()->route('assessments.scores', $assessment)->with('status', 'Assessment updated.');
    }

    /**
     * Store an uploaded question paper, if one was sent.
     *
     * Returns the attributes to merge, or an empty array so an edit that does
     * not touch the file leaves the existing one alone. The file goes to the
     * **private** disk and is only ever streamed through `downloadQuestion()` -
     * a question paper is not something to leave on a guessable public URL
     * before the exam has been sat.
     *
     * @return array<string, string>
     */
    protected function questionPaper(Request $request, ?Assessment $existing = null): array
    {
        if (! $request->hasFile('question_paper')) {
            return [];
        }

        // Replacing a paper replaces the file, rather than orphaning it.
        if ($existing?->attachment_path) {
            Storage::disk('local')->delete($existing->attachment_path);
        }

        $file = $request->file('question_paper');

        return [
            'attachment_path' => $file->store(
                'assessments/'.$request->user()->school_id,
                'local'
            ),
            'attachment_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Stream the question paper to someone entitled to it.
     *
     * Staff who may see the assessment; the students it was set for; and the
     * guardians of those students, who are the other half of "for both parent
     * and student to see it". Never a direct link - the file is on the private
     * disk precisely so that authorization is checked on every fetch.
     */
    public function downloadQuestion(Request $request, Assessment $assessment): StreamedResponse
    {
        $user = $request->user();

        abort_unless($assessment->school_id === $user->school_id, 404);

        abort_unless(
            $user->can('view', $assessment) || $this->isSetForThisFamily($user, $assessment),
            403,
            'This question paper is not yours to open.'
        );

        abort_unless(
            $assessment->attachment_path && Storage::disk('local')->exists($assessment->attachment_path),
            404
        );

        return Storage::disk('local')->download(
            $assessment->attachment_path,
            $assessment->attachment_name ?: 'question-paper',
        );
    }

    /** Is this account a student the work was set for, or their guardian? */
    protected function isSetForThisFamily($user, Assessment $assessment): bool
    {
        if ($student = $user->studentProfile) {
            return Student::inSection($assessment->section_id)->whereKey($student->id)->exists();
        }

        $guardian = $user->guardianProfile;

        if ($guardian === null) {
            return false;
        }

        // Only for a child this guardian is cleared to see academics for.
        return $guardian->students()->get()->contains(
            fn (Student $child) => $guardian->canViewAcademicsFor($child)
                && Student::inSection($assessment->section_id)->whereKey($child->id)->exists()
        );
    }

    /** The mark sheet: one row per student in the section. */
    public function scores(Assessment $assessment): View
    {
        $this->authorize('view', $assessment);

        $assessment->load(['subject', 'section.schoolClass', 'teacher']);

        $students = Student::inSection($assessment->section_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $scores = AssessmentScore::where('assessment_id', $assessment->id)
            ->get()
            ->keyBy('student_id');

        return view('assessments.scores', [
            'assessment' => $assessment,
            'students' => $students,
            'scores' => $scores,
            'scale' => GradeScale::orderBy('sequence')->get(),
            'canEnter' => auth()->user()->can('enterScores', $assessment),
            'canApprove' => auth()->user()->can('approve', $assessment),
            // Only relevant for assignments, where students hand work in.
            'submissions' => $assessment->type === 'assignment'
                ? AssignmentSubmission::where('assessment_id', $assessment->id)->get()->keyBy('student_id')
                : collect(),
            'summary' => $this->summarise($assessment, $scores, $students->count()),
        ]);
    }

    public function saveScores(Request $request, Assessment $assessment, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('enterScores', $assessment);

        $data = $request->validate([
            'scores' => ['array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$assessment->max_score],
            'remarks' => ['array'],
            'remarks.*' => ['nullable', 'string', 'max:255'],
        ], [
            'scores.*.max' => "A mark cannot be higher than the maximum of {$assessment->max_score}.",
            'scores.*.min' => 'A mark cannot be negative.',
        ]);

        DB::transaction(function () use ($assessment, $data, $request) {
            foreach ($data['scores'] ?? [] as $studentId => $score) {
                // Only students actually in this section may be marked.
                $student = Student::inSection($assessment->section_id)->find($studentId);

                if ($student === null) {
                    continue;
                }

                if ($score === null || $score === '') {
                    AssessmentScore::where('assessment_id', $assessment->id)
                        ->where('student_id', $student->id)
                        ->delete();

                    continue;
                }

                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                    [
                        'school_id' => $assessment->school_id,
                        'score' => $score,
                        'remark' => $data['remarks'][$studentId] ?? null,
                        'recorded_by' => $request->user()->id,
                    ],
                );
            }
        });

        $audit->log('grades_entered', 'Examinations & grades',
            "Marks were saved for \"{$assessment->title}\".", $assessment);

        return back()->with('status', 'Marks saved.');
    }

    /** Hand the marks to the academic office. */
    public function submit(Assessment $assessment, AuditLogger $audit, Notifier $notifier): RedirectResponse
    {
        $this->authorize('submit', $assessment);

        $entered = AssessmentScore::where('assessment_id', $assessment->id)->count();

        if ($entered === 0) {
            return back()->withErrors(['scores' => 'Enter at least one mark before submitting.']);
        }

        $assessment->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'review_note' => null,
        ]);

        $audit->log('submitted', 'Examinations & grades',
            "Assessment \"{$assessment->title}\" was submitted for approval.", $assessment);

        $notifier->notifyPermission('grades.approve', new GradesAwaitingApproval($assessment));

        return redirect()
            ->route('assessments.index')
            ->with('status', 'Submitted for approval. The academic office will review the marks.');
    }

    public function destroy(Assessment $assessment, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $assessment);

        $title = $assessment->title;

        $assessment->scores()->delete();
        $assessment->delete();

        $audit->log('deleted', 'Examinations & grades', "Assessment \"{$title}\" was deleted.");

        return redirect()->route('assessments.index')->with('status', 'Assessment deleted.');
    }

    /* ------------------------------------------------------------------ */

    protected function validateAssessment(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Assessment::TYPES)],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:8000'],
            'question_paper' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:8192'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'term_id' => ['nullable', 'integer'],
            'examination_id' => ['nullable', 'integer'],
        ]);
    }

    protected function teacherFor(Request $request): ?Teacher
    {
        return Teacher::where('user_id', $request->user()->id)->first();
    }

    /** Sections this user may work with: their own, or all for the academic office. */
    protected function sectionsFor(Request $request, ?Teacher $teacher): Collection
    {
        $query = Section::with('schoolClass');

        if ($teacher && ! $request->user()->hasAnyPermission(['grades.approve', 'exams.manage'])) {
            $query->whereIn('id', TeachingAssignment::where('teacher_id', $teacher->id)->pluck('section_id'));
        }

        return $query->get()->sortBy('full_name')->values();
    }

    protected function subjectsFor(Request $request, ?Teacher $teacher): Collection
    {
        $query = Subject::query();

        if ($teacher && ! $request->user()->hasAnyPermission(['grades.approve', 'exams.manage'])) {
            $query->whereIn('id', TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id'));
        }

        return $query->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    protected function summarise(Assessment $assessment, Collection $scores, int $studentCount): array
    {
        $values = $scores->pluck('score')->filter(fn ($score) => $score !== null)->map(fn ($s) => (float) $s);

        return [
            'entered' => $values->count(),
            'missing' => max(0, $studentCount - $values->count()),
            'average' => $values->isEmpty() ? null : round($values->avg(), 1),
            'highest' => $values->max(),
            'lowest' => $values->min(),
            'passRate' => $values->isEmpty() ? null : round(
                $values->filter(fn (float $s) => ($s / $assessment->max_score) * 100 >= 60)->count() / $values->count() * 100
            ),
        ];
    }
}
