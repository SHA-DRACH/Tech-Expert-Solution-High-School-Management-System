<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Who teaches what, to whom (spec section 30).
 *
 * This is not paperwork. `AssessmentPolicy` decides whether a teacher may enter
 * marks by looking this table up, so until an assignment exists a teacher
 * cannot record a single grade for that subject. It had no screen at all -
 * assignments arrived only from a seeder - which meant a real school could not
 * give a new teacher a class.
 *
 * A teacher may hold as many assignments as they like: several subjects in one
 * section, the same subject across several sections, or both.
 */
class TeachingAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('academics.view'), 403);

        $year = AcademicYear::active();

        $assignments = TeachingAssignment::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->with(['teacher:id,first_name,last_name,staff_number', 'section.schoolClass', 'subject:id,name,code'])
            ->get()
            ->sortBy(fn (TeachingAssignment $a) => [$a->teacher?->last_name, $a->subject?->name])
            ->values();

        return view('academics.assignments', [
            'year' => $year,
            'assignments' => $assignments,
            'grouped' => $assignments->groupBy('teacher_id'),
            'teachers' => Teacher::where('status', 'active')->orderBy('last_name')->get(),
            'sections' => Section::with('schoolClass')->get()
                ->sortBy(fn (Section $s) => $s->full_name)->values(),
            'subjects' => Subject::orderBy('name')->get(),
            // Staff with nothing to teach yet: the reason a teacher signs in and
            // finds an empty workspace, so it is worth showing plainly.
            'unassigned' => Teacher::where('status', 'active')
                ->whereDoesntHave('teachingAssignments', fn ($query) => $year
                    ? $query->where('academic_year_id', $year->id)
                    : $query)
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);

        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'section_id' => ['required', 'array', 'min:1'],
            'section_id.*' => ['integer'],
            'subject_id' => ['required', 'array', 'min:1'],
            'subject_id.*' => ['integer'],
        ], [], [
            'section_id' => 'class',
            'subject_id' => 'subject',
        ]);

        $year = AcademicYear::active();

        if ($year === null) {
            return back()->withErrors(['teacher_id' => 'Set up an academic year before assigning teaching.']);
        }

        // Every id is re-resolved through the tenant-scoped query, so one from
        // another school does not resolve and nothing is created (section 59).
        $teacher = Teacher::findOrFail($data['teacher_id']);
        $sections = Section::whereIn('id', $data['section_id'])->get();
        $subjects = Subject::whereIn('id', $data['subject_id'])->get();

        if ($sections->isEmpty() || $subjects->isEmpty()) {
            return back()->withErrors(['teacher_id' => 'Select classes and subjects that belong to this school.']);
        }

        $created = 0;

        DB::transaction(function () use ($teacher, $sections, $subjects, $year, &$created) {
            /*
             | Every combination of the classes and subjects ticked, which is how
             | a teacher who takes Mathematics across three sections is set up in
             | one go. firstOrCreate rather than create: re-submitting the form
             | must not double-assign, and a duplicate row here would show the
             | teacher the same class twice in their own workspace.
             */
            foreach ($sections as $section) {
                foreach ($subjects as $subject) {
                    $assignment = TeachingAssignment::firstOrCreate([
                        'academic_year_id' => $year->id,
                        'teacher_id' => $teacher->id,
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                    ], ['school_id' => $teacher->school_id]);

                    if ($assignment->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

        $audit->log(
            'updated',
            'Academics',
            "{$teacher->full_name} was assigned {$created} teaching ".\Illuminate\Support\Str::plural('slot', $created).'.',
            $teacher,
        );

        return back()->with('status', $created > 0
            ? "{$teacher->full_name} was assigned {$created} ".\Illuminate\Support\Str::plural('class', $created).'.'
            : "{$teacher->full_name} already had every combination you selected.");
    }

    public function destroy(Request $request, TeachingAssignment $assignment, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);
        abort_unless($assignment->school_id === $request->user()->school_id, 403);

        $assignment->load(['teacher', 'section.schoolClass', 'subject']);

        $description = "{$assignment->teacher?->full_name} no longer teaches "
            ."{$assignment->subject?->name} to {$assignment->section?->full_name}.";

        /*
         | Removing an assignment removes the teacher's authority to enter marks
         | for it, but never the marks themselves. Grades already recorded stay
         | exactly where they are - they belong to the student, not to whoever
         | happened to be teaching at the time.
         */
        $assignment->delete();

        $audit->log('deleted', 'Academics', $description, null);

        return back()->with('status', $description.' Marks already recorded are unaffected.');
    }
}
