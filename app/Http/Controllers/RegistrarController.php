<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\DocumentCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The registrar's desk.
 *
 * Everything here already existed somewhere - students, documents, grades,
 * guardians, classes - but spread across six modules a registrar had to
 * remember the names of. The work of the office is one person at a counter
 * holding one child's paperwork, so this puts that work in one place and adds
 * the two things it was actually missing: a student record that prints, and a
 * way to attach a parent to a child without editing the parent instead.
 *
 * No new authority is invented. Every action re-checks the same permission the
 * module behind it checks, so putting a link here cannot widen what anyone can
 * reach.
 */
class RegistrarController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('students.view'), 403);

        $year = AcademicYear::active();

        /*
         | Class sizes, counted in the database rather than by loading every
         | enrolment. Withdrawn and archived students are excluded: a roll that
         | counts children who left flatters the number and misleads whoever
         | plans seating from it.
         */
        $classes = SchoolClass::query()
            ->with(['sections' => fn ($query) => $query->orderBy('name')])
            ->withCount(['enrollments as students_count' => fn ($query) => $query
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->whereHas('student', fn ($q) => $q->where('status', 'active'))])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return view('registrar.index', [
            'year' => $year,
            'classes' => $classes,
            'totalStudents' => Student::where('status', 'active')->count(),
            // A child with nobody to telephone is the registrar's problem to
            // fix, so it is surfaced rather than left to be discovered.
            'withoutGuardian' => Student::where('status', 'active')
                ->whereDoesntHave('guardians')
                ->count(),
            'unplaced' => $year
                ? Student::where('status', 'active')
                    ->whereDoesntHave('enrollments', fn ($query) => $query->where('academic_year_id', $year->id))
                    ->count()
                : 0,
        ]);
    }

    /**
     * The student's record, ready to print.
     *
     * One sheet holding what a registrar is asked for at a counter: identity,
     * placement, guardians, and the documents on file. It carries a
     * verification code for the same reason the receipt does - a record that
     * leaves the building should be checkable without a telephone call.
     */
    public function record(Request $request, Student $student): View
    {
        $this->authorize('view', $student);

        $student->load([
            'guardians',
            'enrollments.section.schoolClass',
            'enrollments.academicYear',
            'documents.type',
        ]);

        return view('registrar.record', [
            'student' => $student,
            'school' => $request->user()->school,
            'year' => AcademicYear::active(),
            'verifyCode' => app(DocumentCode::class)
                ->forDocument('student-record', $student->student_number),
        ]);
    }

    /**
     * Attach a parent or guardian to a student.
     *
     * Deliberately its own action rather than a field on the student form: the
     * link carries its own terms - who is primary, and what they may see - and
     * burying those inside a general edit is how a parent ends up able to read
     * a child's finances because somebody was updating an address.
     */
    public function attachGuardian(Request $request, Student $student, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'guardian_id' => ['required', 'integer'],
            'relationship' => ['required', 'string', 'max:60'],
            'is_primary' => ['nullable', 'boolean'],
            'can_view_academics' => ['nullable', 'boolean'],
            'can_view_finance' => ['nullable', 'boolean'],
        ]);

        // Through the scoped query, so a guardian from another school is not
        // findable here whatever id the form carried.
        $guardian = Guardian::findOrFail($data['guardian_id']);

        $primary = $request->boolean('is_primary');

        if ($primary) {
            // One primary contact per child, or "who do we ring first?" has no
            // answer at the moment it is asked.
            $student->guardians()->newPivotQuery()
                ->where('student_id', $student->id)
                ->update(['is_primary' => false]);
        }

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship' => $data['relationship'],
                'is_primary' => $primary,
                'can_view_academics' => $request->boolean('can_view_academics'),
                'can_view_finance' => $request->boolean('can_view_finance'),
            ],
        ]);

        $audit->log('guardian_linked', 'Students',
            $guardian->full_name.' was linked to '.$student->full_name.' as '.$data['relationship'].'.',
            $student);

        return back()->with('status', $guardian->full_name.' is now linked to '.$student->first_name.'.');
    }

    /** Remove a link that should not have been made. */
    public function detachGuardian(Request $request, Student $student, Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $student);

        abort_unless($guardian->school_id === $student->school_id, 403);

        $student->guardians()->detach($guardian->id);

        $audit->log('guardian_unlinked', 'Students',
            $guardian->full_name.' was unlinked from '.$student->full_name.'.',
            $student);

        return back()->with('status', 'Guardian unlinked.');
    }

    /**
     * Change the terms of an existing link.
     *
     * Separate from attaching because this is the one that changes what a
     * parent can see, and it should read as its own decision in the audit log.
     */
    public function updateGuardian(Request $request, Student $student, Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $student);

        abort_unless($guardian->school_id === $student->school_id, 403);

        $request->validate([
            'relationship' => ['required', 'string', 'max:60'],
        ]);

        if ($request->boolean('is_primary')) {
            $student->guardians()->newPivotQuery()
                ->where('student_id', $student->id)
                ->update(['is_primary' => false]);
        }

        $student->guardians()->updateExistingPivot($guardian->id, [
            'relationship' => $request->string('relationship')->toString(),
            'is_primary' => $request->boolean('is_primary'),
            'can_view_academics' => $request->boolean('can_view_academics'),
            'can_view_finance' => $request->boolean('can_view_finance'),
        ]);

        $audit->log('guardian_updated', 'Students',
            'The link between '.$guardian->full_name.' and '.$student->full_name.' was changed.',
            $student);

        return back()->with('status', 'Link updated.');
    }
}
