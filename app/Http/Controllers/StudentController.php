<?php

namespace App\Http\Controllers;

use App\Actions\GrantPortalAccess;
use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\ReferenceNumberGenerator;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Queries here carry no school_id filter on purpose: SchoolScope applies it to
 * every Student and Guardian query, and the policies confirm ownership again
 * for individual records.
 */
class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Student::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString(),
            'class' => $request->integer('class') ?: null,
        ];

        $year = AcademicYear::active();

        $students = Student::query()
            ->with(['guardians:id,first_name,last_name', 'currentEnrollment.section.schoolClass'])
            ->search($filters['search'])
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            /*
             | Filtered by this year's placement rather than by any placement
             | ever made: a child who was in Grade 9 last year and Grade 10 now
             | should appear under one of them, not both.
             */
            ->when($filters['class'], fn ($query, $class) => $query->whereHas(
                'enrollments',
                fn ($inner) => $inner
                    ->where('school_class_id', $class)
                    ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'filters' => $filters,
            'statuses' => Student::STATUSES,
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Student::class);

        $roles = $request->user()->hasPermission('users.create')
            ? Role::inCurrentSchool()->orderBy('name')->get()
            : collect();

        return view('students.create', [
            'guardians' => Guardian::orderBy('last_name')->get(),
            'roles' => $roles,
            'defaultRole' => $roles->firstWhere('slug', 'student'),
            // Placing the student here saves a second step that was easy to
            // skip, and skipping it left a child on no register at all.
            'sections' => Section::with('schoolClass')->get()
                ->sortBy(fn (Section $s) => $s->full_name)->values(),
            'currentYear' => AcademicYear::active(),
        ]);
    }

    public function store(
        StoreStudentRequest $request,
        ReferenceNumberGenerator $numbers,
        SchoolContext $context,
        GrantPortalAccess $access,
    ): RedirectResponse {
        abort_if(
            GrantPortalAccess::wanted($request) && ! $request->user()->hasPermission('users.create'),
            403,
        );

        $data = $request->validated();

        // Resolving the guardian through the scoped query is what stops a
        // guardian id from another school being attached here.
        $guardian = isset($data['guardian_id'])
            ? Guardian::findOrFail($data['guardian_id'])
            : null;

        $student = DB::transaction(function () use ($data, $guardian, $numbers, $context, $request, $access) {
            $student = Student::create(
                collect($data)
                    ->except(['guardian_id', 'relationship', 'section_id', 'account_email', 'account_password', 'account_role_id'])
                    ->all()
                + ['student_number' => $numbers->studentNumber($context->school())]
            );

            if ($guardian) {
                $student->guardians()->attach($guardian->id, [
                    'relationship' => $data['relationship'] ?? 'Guardian',
                    'is_primary' => true,
                ]);
            }

            /*
             | Place the student straight away when a class was chosen. Adding
             | a student and then placing them was two steps with nothing
             | joining them, and skipping the second left a child who could not
             | be marked, registered or reported on at all.
             */
            if (filled($data['section_id'] ?? null) && $year = AcademicYear::active()) {
                $section = Section::with('schoolClass')->find($data['section_id']);

                if ($section) {
                    Enrollment::create([
                        'school_id' => $student->school_id,
                        'student_id' => $student->id,
                        'academic_year_id' => $year->id,
                        'school_class_id' => $section->school_class_id,
                        'section_id' => $section->id,
                        'status' => 'active',
                        'enrolled_on' => now()->toDateString(),
                    ]);

                    // Every subject the class offers; electives are deselected
                    // afterwards on the student's own record.
                    $offered = $section->schoolClass?->subjects()->pluck('subjects.id') ?? collect();

                    $student->subjects()->attach(
                        $offered->mapWithKeys(fn (int $id) => [$id => [
                            'school_id' => $student->school_id,
                            'academic_year_id' => $year->id,
                        ]])->all()
                    );
                }
            }

            $access->handle($request, $student->full_name, ['student_id' => $student->id]);

            return $student;
        });

        return redirect()
            ->route('students.index')
            ->with('status', "Student {$student->student_number} was created.");
    }

    public function show(Request $request, Student $student): View
    {
        $this->authorize('view', $student);

        $student->load(['guardians', 'user', 'enrollments.section.schoolClass', 'enrollments.academicYear']);

        $year = AcademicYear::active();

        $current = $student->enrollments->firstWhere('academic_year_id', $year?->id);

        return view('students.show', [
            'student' => $student,
            'year' => $year,
            'current' => $current,
            'canEnrol' => $request->user()->hasPermission('students.update'),
            // Everyone on record, so a parent can be linked from the child's
            // page rather than by editing the parent and hunting for the child.
            'guardians' => Guardian::orderBy('last_name')->orderBy('first_name')->get(),
            'sections' => Section::with('schoolClass')->get()
                ->sortBy(fn (Section $s) => $s->full_name)->values(),
            'years' => AcademicYear::orderByDesc('starts_on')->get(),
            // What the class offers, and what this student is down for.
            'offered' => $current?->section?->schoolClass?->subjects()->orderBy('name')->get() ?? collect(),
            'taken' => $year ? $student->subjectsFor($year)->pluck('subjects.id') : collect(),
        ]);
    }

    public function edit(Student $student): View
    {
        $this->authorize('update', $student);

        return view('students.edit', [
            'student' => $student->load('guardians'),
            'guardians' => Guardian::orderBy('last_name')->get(),
            'statuses' => Student::STATUSES,
        ]);
    }

    public function update(Request $request, Student $student, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(Student::STATUSES)],
            'guardian_id' => ['nullable', 'integer'],
            'relationship' => ['nullable', 'string', 'max:60'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        /*
         | The student number is deliberately not editable. It is printed on
         | report cards, quoted on invoices and used to match uploaded marks;
         | changing it would silently orphan every one of those references.
         */
        $attributes = collect($data)->except(['guardian_id', 'relationship', 'photo'])->all();

        if ($request->hasFile('photo')) {
            $previous = $student->photo_path;

            $attributes['photo_path'] = $request->file('photo')->store('students', 'public');

            // The old file is removed only once the new one is stored, so a
            // failed upload never leaves the record with no photograph.
            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $original = $student->only(array_keys(collect($data)->except(['guardian_id', 'relationship', 'photo'])->all()));

        DB::transaction(function () use ($student, $data, $attributes) {
            $student->update($attributes);

            if (filled($data['guardian_id'] ?? null)) {
                // Re-resolved through the scoped query, so a guardian from
                // another school cannot be attached.
                $guardian = Guardian::findOrFail($data['guardian_id']);

                // syncWithoutDetaching: adding a guardian must not quietly drop
                // the others a child already has.
                $student->guardians()->syncWithoutDetaching([
                    $guardian->id => [
                        'relationship' => $data['relationship'] ?? 'Guardian',
                        'is_primary' => $student->guardians->isEmpty(),
                    ],
                ]);
            }
        });

        $audit->log('updated', 'Students', "{$student->full_name}'s record was updated.", $student, $original, $data);

        return redirect()
            ->route('students.show', $student)
            ->with('status', "{$student->full_name}'s record was updated.");
    }

    /**
     * Archive, never delete (spec section 71.10).
     *
     * A student record anchors enrolments, marks, attendance, invoices and
     * report cards. Deleting it would leave all of that pointing at nobody, so
     * the record is marked archived and soft-deleted, and can be restored.
     */
    public function destroy(Student $student, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('archive', $student);

        $name = $student->full_name;

        DB::transaction(function () use ($student) {
            $student->update(['status' => 'archived']);
            $student->delete();
        });

        $audit->log('archived', 'Students', "{$name} was archived.", $student);

        return redirect()
            ->route('students.index')
            ->with('status', "{$name} was archived. Their record and history are kept.");
    }

    public function restore(Request $request, int $student, AuditLogger $audit): RedirectResponse
    {
        // Route model binding excludes trashed rows, so this one is fetched by
        // hand - still through the tenant-scoped query.
        $record = Student::onlyTrashed()->findOrFail($student);

        $this->authorize('archive', $record);

        $record->restore();
        $record->update(['status' => 'active']);

        $audit->log('restored', 'Students', "{$record->full_name} was restored.", $record);

        return back()->with('status', "{$record->full_name} was restored.");
    }
}
