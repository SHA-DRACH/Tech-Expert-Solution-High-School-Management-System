<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use App\Services\StudentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assignments in the student portal (spec section 23).
 *
 * Two separate switches govern this: `view_assignments` to see the list at all,
 * and `submit_assignments` to hand work in. A school that wants students to
 * read their assignments but deliver them on paper turns the second one off.
 */
class StudentAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $student = $this->student($request);
        $abilities = $this->access()->for($student);

        $this->authorizeAbility($student, 'view_assignments');

        $sectionId = $student->currentEnrollment?->section_id;

        $assignments = Assessment::query()
            ->where('type', 'assignment')
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->when(! $sectionId, fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['subject:id,name', 'teacher:id,first_name,last_name'])
            ->orderByDesc('ends_at')
            ->get();

        $submissions = AssignmentSubmission::where('student_id', $student->id)
            ->whereIn('assessment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        return view('portals.student.assignments', [
            'student' => $student,
            'abilities' => $abilities,
            'assignments' => $assignments,
            'submissions' => $submissions,
            'canSubmit' => $abilities['submit_assignments'],
        ]);
    }

    public function store(Request $request, Assessment $assessment): RedirectResponse
    {
        $student = $this->student($request);

        $this->authorizeAbility($student, 'submit_assignments');
        $this->guardAssignment($student, $assessment);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:8000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,txt', 'max:8192'],
        ]);

        if (blank($data['body'] ?? null) && ! $request->hasFile('attachment')) {
            return back()->withErrors([
                'body' => 'Write something or attach a file before submitting.',
            ]);
        }

        $existing = AssignmentSubmission::where('assessment_id', $assessment->id)
            ->where('student_id', $student->id)
            ->first();

        $attributes = [
            'school_id' => $student->school_id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'body' => $data['body'] ?? null,
            'submitted_at' => now(),
            'status' => 'submitted',
        ];

        if ($request->hasFile('attachment')) {
            // Replacing a submission replaces its file too.
            if ($existing?->attachment_path) {
                Storage::disk('local')->delete($existing->attachment_path);
            }

            $file = $request->file('attachment');

            // Private disk: only the student and their teacher may fetch it.
            $attributes['attachment_path'] = $file->store(
                "submissions/{$student->school_id}/{$assessment->id}",
                'local'
            );
            $attributes['original_name'] = $file->getClientOriginalName();
        }

        AssignmentSubmission::updateOrCreate(
            ['assessment_id' => $assessment->id, 'student_id' => $student->id],
            $attributes,
        );

        return back()->with('status', 'Your work has been submitted.');
    }

    /** Streamed, never linked to directly. */
    public function download(Request $request, AssignmentSubmission $submission): StreamedResponse
    {
        $user = $request->user();

        $isOwner = $submission->student?->user_id === $user->id;
        $isTeacher = $user->hasAnyPermission(['grades.enter', 'grades.approve'])
            && $submission->school_id === $user->school_id;

        abort_unless($isOwner || $isTeacher, 403);
        abort_unless($submission->attachment_path && Storage::disk('local')->exists($submission->attachment_path), 404);

        return Storage::disk('local')->download(
            $submission->attachment_path,
            $submission->original_name ?? 'submission'
        );
    }

    /* ------------------------------------------------------------------ */

    protected function student(Request $request): Student
    {
        $student = $request->user()->studentProfile()->with('currentEnrollment')->first();

        abort_unless($student !== null, 403, 'This account is not linked to a student record.');

        return $student;
    }

    protected function authorizeAbility(Student $student, string $ability): void
    {
        abort_unless(
            $this->access()->allows($student, $ability),
            403,
            'Your school has not enabled this part of the student portal.'
        );
    }

    /** The assignment must belong to this student's own class, and still be open. */
    protected function guardAssignment(Student $student, Assessment $assessment): void
    {
        abort_unless($assessment->type === 'assignment', 404);

        abort_unless(
            $assessment->section_id === $student->currentEnrollment?->section_id,
            403,
            'That assignment was not set for your class.'
        );

        abort_if(
            $assessment->status === 'approved',
            422,
            'This assignment has already been marked and closed.'
        );
    }

    protected function access(): StudentAccess
    {
        return app(StudentAccess::class);
    }
}
