<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Subject management (spec section 29).
 *
 * Section 29 asks for five things on a subject: name, code, department, grade
 * and assigned teachers. Only the first three had anywhere to be entered.
 *
 * The missing pair mattered more than it looked. Which grades take a subject
 * lives in `class_subject`, and **nothing in the application wrote to that
 * table** - it was populated by the seeder and by nothing else. A school that
 * added "Further Mathematics" got a row in `subjects` that belonged to no
 * class, so it never appeared on a mark sheet, never reached a report card, and
 * looked for all the world like the software had lost it.
 */
class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('academics.view'), 403);

        $year = AcademicYear::active();

        $subjects = Subject::with(['department:id,name', 'schoolClasses:id,name,level'])
            ->orderBy('name')
            ->get();

        // Teaching, grouped per subject, so each row can say who takes it.
        $assignments = TeachingAssignment::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->with(['teacher:id,first_name,last_name', 'section.schoolClass'])
            ->get()
            ->groupBy('subject_id');

        return view('subjects.index', [
            'subjects' => $subjects,
            'assignments' => $assignments,
            'departments' => Department::orderBy('name')->get(),
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
            'sections' => Section::with('schoolClass')->get()
                ->sortBy(fn (Section $s) => $s->full_name)->values(),
            'teachers' => Teacher::where('status', 'active')->orderBy('last_name')->get(),
            'year' => $year,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);

        $subject = DB::transaction(function () use ($data, $request) {
            $subject = Subject::create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'department_id' => $this->department($data),
                'description' => $data['description'] ?? null,
                'is_core' => $request->boolean('is_core'),
            ]);

            $this->syncGrades($subject, $data['class_ids'] ?? []);

            return $subject;
        });

        $audit->log('created', 'Academics', "Subject {$subject->name} was created.", $subject);

        return back()->with('status', "{$subject->name} was created."
            .(empty($data['class_ids'])
                // Said now rather than left to be discovered later, when the
                // subject fails to appear on a mark sheet.
                ? ' It is not attached to any grade yet, so it will not appear on a mark sheet until it is.'
                : ''));
    }

    public function update(Request $request, Subject $subject, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertOwned($request, $subject);

        $data = $this->validated($request, $subject);

        $original = $subject->only(['name', 'code', 'department_id', 'is_core']);

        DB::transaction(function () use ($subject, $data, $request) {
            $subject->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'department_id' => $this->department($data),
                'description' => $data['description'] ?? null,
                'is_core' => $request->boolean('is_core'),
            ]);

            $this->syncGrades($subject, $data['class_ids'] ?? []);
        });

        $audit->log('updated', 'Academics', "Subject {$subject->name} was updated.", $subject, $original, $data);

        return back()->with('status', "{$subject->name} was updated.");
    }

    public function destroy(Request $request, Subject $subject, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertOwned($request, $subject);

        $assessments = DB::table('assessments')->where('subject_id', $subject->id)->count();

        if ($assessments > 0) {
            // Marks are filed against a subject; removing it would leave them
            // pointing at nothing (section 71.10).
            return back()->withErrors([
                'subject' => "{$subject->name} cannot be archived: {$assessments} "
                    .\Illuminate\Support\Str::plural('assessment', $assessments)
                    .' still refer to it. Academic history is kept, not removed.',
            ]);
        }

        $name = $subject->name;

        DB::transaction(function () use ($subject) {
            // The teaching stops; the subject record is archived, not deleted.
            TeachingAssignment::where('subject_id', $subject->id)->delete();
            $subject->schoolClasses()->detach();
            $subject->delete();
        });

        $audit->log('archived', 'Academics', "Subject {$name} was archived.", null);

        return back()->with('status', "{$name} was archived.");
    }

    /**
     * Give a teacher this subject in one or more classes.
     *
     * The same table the teaching-assignments screen writes, reached from the
     * subject instead: "who teaches Chemistry?" and "what does Grace teach?"
     * are the same question asked from two ends, and a school asks both.
     */
    public function assignTeacher(Request $request, Subject $subject, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertOwned($request, $subject);

        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'section_id' => ['required', 'array', 'min:1'],
            'section_id.*' => ['integer'],
        ], [], ['section_id' => 'class']);

        $year = AcademicYear::active();

        if ($year === null) {
            return back()->withErrors(['teacher_id' => 'Set up an academic year before assigning teaching.']);
        }

        // Both re-resolved through the tenant-scoped query (section 59).
        $teacher = Teacher::findOrFail($data['teacher_id']);
        $sections = Section::whereIn('id', $data['section_id'])->get();

        if ($sections->isEmpty()) {
            return back()->withErrors(['section_id' => 'Select classes that belong to this school.']);
        }

        $created = 0;

        DB::transaction(function () use ($teacher, $sections, $subject, $year, &$created) {
            foreach ($sections as $section) {
                $assignment = TeachingAssignment::firstOrCreate([
                    'academic_year_id' => $year->id,
                    'teacher_id' => $teacher->id,
                    'section_id' => $section->id,
                    'subject_id' => $subject->id,
                ], ['school_id' => $subject->school_id]);

                if ($assignment->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        $audit->log('updated', 'Academics',
            "{$teacher->full_name} was assigned {$subject->name} in {$created} "
            .\Illuminate\Support\Str::plural('class', $created).'.', $subject);

        return back()->with('status', $created > 0
            ? "{$teacher->full_name} now teaches {$subject->name} in {$created} "
                .\Illuminate\Support\Str::plural('class', $created).'.'
            : "{$teacher->full_name} already taught {$subject->name} in every class you selected.");
    }

    /* ------------------------------------------------------------ helpers */

    protected function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);
    }

    protected function assertOwned(Request $request, Subject $subject): void
    {
        abort_unless($subject->school_id === $request->user()->school_id, 403);
    }

    protected function validated(Request $request, ?Subject $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')
                ->where('school_id', $request->user()->school_id)
                ->ignore($existing?->id)],
            'department_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_core' => ['boolean'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
        ], [
            'code.unique' => 'Another subject already uses that code.',
        ], [
            'class_ids' => 'grades',
            'department_id' => 'department',
        ]);
    }

    protected function department(array $data): ?int
    {
        // Resolved through the scoped query, so a department from another
        // school does not resolve and cannot be attached.
        return filled($data['department_id'] ?? null)
            ? Department::findOrFail($data['department_id'])->id
            : null;
    }

    /**
     * Attach the subject to the grades that take it.
     *
     * `sync` on purpose: unticking a grade means that grade no longer takes the
     * subject. Marks already recorded are untouched - they hang off the
     * assessment, not off this pivot.
     */
    protected function syncGrades(Subject $subject, array $classIds): void
    {
        $classes = SchoolClass::whereIn('id', $classIds)->pluck('id');

        $subject->schoolClasses()->sync(
            $classes->mapWithKeys(fn (int $id) => [$id => ['school_id' => $subject->school_id]])->all()
        );
    }
}
