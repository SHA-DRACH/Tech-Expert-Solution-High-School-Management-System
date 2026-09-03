<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Placing a student in a class, and recording the subjects they take.
 *
 * Nothing in the application created an enrolment. Not the student form, not
 * admissions approval - the rows existed because a seeder wrote them. A school
 * could add a student and had no way to put them in a class, so the child
 * showed "Not assigned" for ever: no mark sheet, no register, no report card,
 * no timetable.
 *
 * Subjects are recorded alongside, defaulting to everything the class offers.
 * That is what the software used to infer, so the default keeps today's
 * behaviour; deselecting is what makes an elective possible.
 */
class EnrollmentController extends Controller
{
    public function store(Request $request, Student $student, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'roll_number' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['active', 'transferred', 'completed', 'withdrawn'])],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer'],
        ], [], [
            'section_id' => 'class',
            'academic_year_id' => 'academic year',
        ]);

        // Both re-resolved through the tenant-scoped query, so an id from
        // another school does not resolve and nothing is written (section 59).
        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $section = Section::with('schoolClass')->findOrFail($data['section_id']);

        $existing = Enrollment::where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->first();

        $moved = $existing && $existing->section_id !== $section->id;

        DB::transaction(function () use ($student, $year, $section, $data, $existing) {
            /*
             | One placement per student per year, updated rather than added to.
             | Two active enrolments in one year would make "which class is this
             | child in?" unanswerable, and attendance, marks and report cards
             | all ask it.
             */
            $enrollment = Enrollment::updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $year->id],
                [
                    'school_id' => $student->school_id,
                    'school_class_id' => $section->school_class_id,
                    'section_id' => $section->id,
                    'roll_number' => $data['roll_number'] ?? $existing?->roll_number,
                    'status' => $data['status'] ?? 'active',
                    'enrolled_on' => $existing?->enrolled_on ?? now()->toDateString(),
                ],
            );

            $this->syncSubjects($student, $section, $year, $data['subject_ids'] ?? null);

            return $enrollment;
        });

        $audit->log(
            $existing ? 'updated' : 'created',
            'Students',
            $moved
                ? "{$student->full_name} was moved to {$section->full_name} for {$year->name}."
                : "{$student->full_name} was enrolled in {$section->full_name} for {$year->name}.",
            $student,
            $existing ? ['section_id' => $existing->section_id] : null,
            ['section_id' => $section->id, 'academic_year_id' => $year->id],
        );

        return back()->with('status', $moved
            ? "{$student->full_name} moved to {$section->full_name}."
            : "{$student->full_name} enrolled in {$section->full_name} for {$year->name}.");
    }

    /** Change only the subjects, leaving the placement alone. */
    public function updateSubjects(Request $request, Student $student, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer'],
        ]);

        $year = AcademicYear::findOrFail($data['academic_year_id']);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->with('section.schoolClass')
            ->first();

        if ($enrollment?->section === null) {
            return back()->withErrors([
                'subject_ids' => 'Place this student in a class first — the subjects on offer come from the class.',
            ]);
        }

        $before = $student->subjectsFor($year)->pluck('subjects.name')->sort()->values()->all();

        $this->syncSubjects($student, $enrollment->section, $year, $data['subject_ids'] ?? []);

        $after = $student->subjectsFor($year)->pluck('subjects.name')->sort()->values()->all();

        $audit->log(
            'updated',
            'Students',
            "{$student->full_name}'s subjects for {$year->name} were changed.",
            $student,
            ['subjects' => $before],
            ['subjects' => $after],
        );

        return back()->with('status', "Subjects updated for {$student->full_name}.");
    }

    public function destroy(Request $request, Enrollment $enrollment, AuditLogger $audit): RedirectResponse
    {
        $student = $enrollment->student;

        abort_unless($student !== null, 404);

        $this->authorize('update', $student);
        abort_unless($enrollment->school_id === $request->user()->school_id, 403);

        $marks = DB::table('assessment_scores')
            ->join('assessments', 'assessments.id', '=', 'assessment_scores.assessment_id')
            ->where('assessment_scores.student_id', $student->id)
            ->where('assessments.section_id', $enrollment->section_id)
            ->count();

        if ($marks > 0) {
            // Removing the placement would leave those marks attached to a
            // class the child was never in.
            return back()->withErrors([
                'enrollment' => "This placement cannot be removed: {$marks} "
                    .\Illuminate\Support\Str::plural('mark', $marks)
                    .' were recorded against it. Change the class instead.',
            ]);
        }

        $description = "{$student->full_name}'s placement in "
            .($enrollment->section?->full_name ?? 'a class').' was removed.';

        DB::transaction(function () use ($enrollment, $student) {
            $student->subjects()->wherePivot('academic_year_id', $enrollment->academic_year_id)->detach();

            $enrollment->delete();
        });

        $audit->log('deleted', 'Students', $description, $student);

        return back()->with('status', $description);
    }

    /**
     * Record the subjects a student takes.
     *
     * A null selection means "everything the class offers", which is what the
     * system inferred before this table existed, so a form that does not ask
     * about subjects still produces the old behaviour. Anything not on offer to
     * the class is dropped rather than attached: a student cannot take a
     * subject their class does not run.
     */
    protected function syncSubjects(Student $student, Section $section, AcademicYear $year, ?array $subjectIds): void
    {
        $offered = $section->schoolClass?->subjects()->pluck('subjects.id') ?? collect();

        $chosen = $subjectIds === null
            ? $offered
            : $offered->intersect(Subject::whereIn('id', $subjectIds)->pluck('id'));

        $student->subjects()->wherePivot('academic_year_id', $year->id)->detach();

        $student->subjects()->attach(
            $chosen->mapWithKeys(fn (int $id) => [$id => [
                'school_id' => $student->school_id,
                'academic_year_id' => $year->id,
            ]])->all()
        );
    }
}
