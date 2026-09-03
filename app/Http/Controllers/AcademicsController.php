<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The academic structure: years, terms, grade levels, sections and subjects.
 * Everything here is configurable per school; nothing is assumed in code.
 */
class AcademicsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('academics.view'), 403);

        return view('academics.index', [
            'years' => AcademicYear::withCount('enrollments')->orderByDesc('starts_on')->get(),
            'terms' => Term::with('academicYear')->orderBy('sequence')->get(),
            'classes' => SchoolClass::withCount(['sections', 'enrollments'])->orderBy('level')->get(),
            'sections' => Section::with(['schoolClass', 'classTeacher'])->withCount('enrollments')->get(),
            'subjects' => Subject::with('department')->orderBy('name')->get(),
            'departments' => Department::withCount(['subjects', 'teachers'])->orderBy('name')->get(),
            'teachers' => Teacher::where('status', 'active')->orderBy('last_name')->get(),
        ]);
    }

    public function storeClass(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('school_classes', 'name')->where('school_id', $request->user()->school_id)],
            'level' => ['nullable', 'integer', 'min:1', 'max:20'],
            'stage' => ['nullable', 'string', 'max:40'],
        ]);

        SchoolClass::create($data);

        return back()->with('status', 'Class created.');
    }

    public function storeSection(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'room' => ['nullable', 'string', 'max:40'],
            'class_teacher_id' => ['nullable', 'integer'],
        ]);

        // Both ids are re-resolved through the tenant-scoped query.
        $class = SchoolClass::findOrFail($data['school_class_id']);
        $teacher = isset($data['class_teacher_id']) ? Teacher::findOrFail($data['class_teacher_id']) : null;

        Section::create([
            'school_class_id' => $class->id,
            'class_teacher_id' => $teacher?->id,
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? null,
            'room' => $data['room'] ?? null,
        ]);

        return back()->with('status', 'Section created.');
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->where('school_id', $request->user()->school_id)],
            'department_id' => ['nullable', 'integer'],
            'is_core' => ['boolean'],
        ]);

        $department = isset($data['department_id']) ? Department::findOrFail($data['department_id']) : null;

        Subject::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'department_id' => $department?->id,
            'is_core' => $request->boolean('is_core'),
        ]);

        return back()->with('status', 'Subject created.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->where('school_id', $request->user()->school_id)],
            'code' => ['nullable', 'string', 'max:20'],
        ]);

        Department::create($data);

        return back()->with('status', 'Department created.');
    }

    /* ------------------------------------------------------------ updating */

    public function updateClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('school_classes', 'name')
                ->where('school_id', $request->user()->school_id)->ignore($schoolClass->id)],
            'level' => ['nullable', 'integer', 'min:1', 'max:20'],
            'stage' => ['nullable', 'string', 'max:40'],
        ]);

        $schoolClass->update($data);

        return back()->with('status', 'Class updated.');
    }

    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'room' => ['nullable', 'string', 'max:40'],
            'class_teacher_id' => ['nullable', 'integer'],
        ]);

        // Both ids are re-resolved through the tenant-scoped query, so one
        // belonging to another school does not resolve and cannot be attached.
        $class = SchoolClass::findOrFail($data['school_class_id']);
        $teacher = filled($data['class_teacher_id'] ?? null) ? Teacher::findOrFail($data['class_teacher_id']) : null;

        $section->update([
            'school_class_id' => $class->id,
            'class_teacher_id' => $teacher?->id,
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? null,
            'room' => $data['room'] ?? null,
        ]);

        return back()->with('status', 'Section updated.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')
                ->where('school_id', $request->user()->school_id)->ignore($subject->id)],
            'department_id' => ['nullable', 'integer'],
            'is_core' => ['boolean'],
        ]);

        $department = filled($data['department_id'] ?? null) ? Department::findOrFail($data['department_id']) : null;

        $subject->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'department_id' => $department?->id,
            'is_core' => $request->boolean('is_core'),
        ]);

        return back()->with('status', 'Subject updated.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')
                ->where('school_id', $request->user()->school_id)->ignore($department->id)],
            'code' => ['nullable', 'string', 'max:20'],
        ]);

        $department->update($data);

        return back()->with('status', 'Department updated.');
    }

    /* ------------------------------------------------------------ removing */

    /*
     | Everything below archives rather than deletes, and refuses outright when
     | the record still anchors history (section 71.10). A class with enrolments
     | against it is not a mistake to be tidied away: last year's report cards
     | name it, and deleting it would leave them pointing at nothing.
     */

    public function destroyClass(Request $request, SchoolClass $schoolClass, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($count = $schoolClass->enrollments()->count()) {
            return $this->refuse('class', $schoolClass->name, $count, 'enrolment');
        }

        if ($count = $schoolClass->sections()->count()) {
            return back()->withErrors(['structure' => "{$schoolClass->name} still has {$count} ".Str::plural('section', $count).'. Archive those first.']);
        }

        $schoolClass->delete();

        $audit->log('archived', 'Academics', "Class {$schoolClass->name} was archived.", $schoolClass);

        return back()->with('status', "{$schoolClass->name} was archived.");
    }

    public function destroySection(Request $request, Section $section, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($count = $section->enrollments()->count()) {
            return $this->refuse('section', $section->full_name, $count, 'enrolment');
        }

        $section->delete();

        $audit->log('archived', 'Academics', "Section {$section->full_name} was archived.", $section);

        return back()->with('status', "{$section->full_name} was archived.");
    }

    public function destroySubject(Request $request, Subject $subject, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $count = DB::table('assessments')->where('subject_id', $subject->id)->count();

        if ($count) {
            return $this->refuse('subject', $subject->name, $count, 'assessment');
        }

        $subject->delete();

        $audit->log('archived', 'Academics', "Subject {$subject->name} was archived.", $subject);

        return back()->with('status', "{$subject->name} was archived.");
    }

    public function destroyDepartment(Request $request, Department $department, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        // Subjects and teachers are only *pointed* at a department, so the
        // department can go without taking them with it - they simply become
        // unassigned, which is a state the rest of the app already handles.
        DB::transaction(function () use ($department) {
            Subject::where('department_id', $department->id)->update(['department_id' => null]);
            Teacher::where('department_id', $department->id)->update(['department_id' => null]);

            $department->delete();
        });

        $audit->log('archived', 'Academics', "Department {$department->name} was archived.", $department);

        return back()->with('status', "{$department->name} was archived. Its subjects and staff are now unassigned.");
    }

    protected function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);
    }

    protected function refuse(string $what, string $name, int $count, string $noun): RedirectResponse
    {
        return back()->withErrors([
            'structure' => "{$name} cannot be archived: {$count} ".Str::plural($noun, $count)
                ." still ".($count === 1 ? 'refers' : 'refer')." to this {$what}. Academic history is kept, not removed.",
        ]);
    }
}
