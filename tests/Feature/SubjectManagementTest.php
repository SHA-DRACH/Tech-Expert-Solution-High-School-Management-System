<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Department;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Subject management (spec section 29).
 *
 * The gap these cover: `class_subject` decides which grades take a subject, and
 * nothing in the application wrote to it. A newly created subject belonged to
 * no class, so it never reached a mark sheet and looked lost.
 */
class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected SchoolClass $grade11;

    protected SchoolClass $grade12;

    protected Department $sciences;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $this->sciences = Department::create(['school_id' => $this->school->id, 'name' => 'Sciences']);

        $this->grade11 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 11', 'level' => 11]);
        $this->grade12 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 12', 'level' => 12]);
    }

    protected function manager()
    {
        return $this->userFor($this->school, ['academics.view', 'academics.manage']);
    }

    /* -------------------------------------------- all five section 29 fields */

    public function test_a_subject_is_created_with_name_code_department_and_grades(): void
    {
        $this->actingAs($this->manager())
            ->post(route('subjects.store'), [
                'name' => 'Further Mathematics',
                'code' => 'fmt201',
                'department_id' => $this->sciences->id,
                'class_ids' => [$this->grade11->id, $this->grade12->id],
                'is_core' => '1',
            ])
            ->assertRedirect();

        $subject = Subject::where('name', 'Further Mathematics')->firstOrFail();

        $this->assertSame('FMT201', $subject->code, 'Codes are stored uppercase.');
        $this->assertSame($this->sciences->id, $subject->department_id);
        $this->assertTrue((bool) $subject->is_core);

        // The part that had no writer anywhere in the application.
        $this->assertEqualsCanonicalizing(
            [$this->grade11->id, $this->grade12->id],
            $subject->schoolClasses->pluck('id')->all(),
        );
    }

    public function test_a_subject_with_no_grade_is_created_but_says_so(): void
    {
        /*
         | Allowed, because a school may add the subject before it decides who
         | takes it — but said now rather than left to be discovered when the
         | subject fails to appear on a mark sheet weeks later.
         */
        $this->actingAs($this->manager())
            ->post(route('subjects.store'), ['name' => 'Latin', 'code' => 'LAT101'])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'not attached to any grade'));

        $this->assertSame(0, Subject::where('code', 'LAT101')->firstOrFail()->schoolClasses()->count());
    }

    public function test_a_subject_reaches_the_mark_sheet_once_it_has_a_grade(): void
    {
        $section = Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $this->grade12->id, 'name' => 'A',
        ]);

        Term::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'name' => 'First Term', 'sequence' => 1,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-12-15', 'is_current' => true,
        ]);

        $user = $this->userFor($this->school, ['academics.view', 'academics.manage', 'grades.enter', 'grades.approve']);

        $this->actingAs($user)->post(route('subjects.store'), [
            'name' => 'Further Mathematics',
            'code' => 'FMT201',
            'class_ids' => [$this->grade12->id],
        ]);

        // The whole point: a new subject is now selectable where marks are entered.
        $this->actingAs($user)
            ->get(route('marks.index', ['section' => $section->id]))
            ->assertOk()
            ->assertSee('Further Mathematics');
    }

    public function test_grades_can_be_changed_and_unticking_one_removes_it(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Physics', 'code' => 'PHY101']);

        $manager = $this->manager();

        $this->actingAs($manager)->put(route('subjects.update', $subject), [
            'name' => 'Physics', 'code' => 'PHY101',
            'class_ids' => [$this->grade11->id, $this->grade12->id],
        ]);

        $this->assertSame(2, $subject->fresh()->schoolClasses()->count());

        // sync, not attach: unticking means that grade no longer takes it.
        $this->actingAs($manager)->put(route('subjects.update', $subject), [
            'name' => 'Physics', 'code' => 'PHY101',
            'class_ids' => [$this->grade12->id],
        ]);

        $this->assertSame([$this->grade12->id], $subject->fresh()->schoolClasses->pluck('id')->all());
    }

    public function test_a_duplicate_code_is_refused(): void
    {
        Subject::create(['school_id' => $this->school->id, 'name' => 'Physics', 'code' => 'PHY101']);

        $this->actingAs($this->manager())
            ->post(route('subjects.store'), ['name' => 'Applied Physics', 'code' => 'PHY101'])
            ->assertSessionHasErrors('code');
    }

    /* ------------------------------------------------- assigned teachers */

    public function test_a_teacher_can_be_assigned_from_the_subject(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Physics', 'code' => 'PHY101']);

        $teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Emmanuel', 'last_name' => 'Toe', 'status' => 'active',
        ]);

        $a = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade12->id, 'name' => 'A']);
        $b = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade12->id, 'name' => 'B']);

        // "Who teaches Physics?" and "what does Emmanuel teach?" are the same
        // question from two ends, and both now have a screen.
        $this->actingAs($this->manager())
            ->post(route('subjects.teachers.store', $subject), [
                'teacher_id' => $teacher->id,
                'section_id' => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, TeachingAssignment::where('subject_id', $subject->id)->count());
    }

    public function test_assigning_the_same_teaching_twice_does_not_duplicate_it(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Physics', 'code' => 'PHY101']);

        $teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Emmanuel', 'last_name' => 'Toe', 'status' => 'active',
        ]);

        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade12->id, 'name' => 'A']);

        $manager = $this->manager();
        $payload = ['teacher_id' => $teacher->id, 'section_id' => [$section->id]];

        $this->actingAs($manager)->post(route('subjects.teachers.store', $subject), $payload);
        $this->actingAs($manager)->post(route('subjects.teachers.store', $subject), $payload);

        $this->assertSame(1, TeachingAssignment::count());
    }

    /* ------------------------------------------------------------ removal */

    public function test_a_subject_holding_marks_cannot_be_archived(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Physics', 'code' => 'PHY101']);

        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade12->id, 'name' => 'A']);

        Assessment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'section_id' => $section->id, 'subject_id' => $subject->id,
            'title' => 'Test', 'type' => 'test', 'max_score' => 100,
        ]);

        // Marks are filed against a subject; removing it would leave them
        // pointing at nothing.
        $this->actingAs($this->manager())
            ->delete(route('subjects.destroy', $subject))
            ->assertSessionHasErrors('subject');

        $this->assertSame(1, Subject::count());
    }

    public function test_an_unused_subject_is_archived_with_its_teaching(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Latin', 'code' => 'LAT101']);
        $subject->schoolClasses()->attach($this->grade12->id, ['school_id' => $this->school->id]);

        $teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Emmanuel', 'last_name' => 'Toe', 'status' => 'active',
        ]);

        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $this->grade12->id, 'name' => 'A']);

        TeachingAssignment::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->year->id,
            'teacher_id' => $teacher->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->manager())
            ->delete(route('subjects.destroy', $subject))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Subject::count());
        $this->assertSame(0, TeachingAssignment::count());
        $this->assertSame(0, DB::table('class_subject')->where('subject_id', $subject->id)->count());
    }

    /* ------------------------------------------------------ authorization */

    public function test_viewing_needs_the_academics_permission(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('subjects.index'))
            ->assertForbidden();
    }

    public function test_changing_needs_the_manage_permission(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Physics', 'code' => 'PHY101']);

        $viewer = $this->userFor($this->school, ['academics.view']);

        $this->actingAs($viewer)->get(route('subjects.index'))->assertOk();

        $this->actingAs($viewer)
            ->put(route('subjects.update', $subject), ['name' => 'Hijacked', 'code' => 'PHY101'])
            ->assertForbidden();
    }

    public function test_a_subject_from_another_school_cannot_be_changed(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = Subject::create(['school_id' => $other->id, 'name' => 'Theirs', 'code' => 'OTH101']);

        $this->actingAs($this->manager())
            ->put(route('subjects.update', $foreign), ['name' => 'Hijacked', 'code' => 'OTH101'])
            ->assertNotFound();

        $this->assertSame('Theirs', $foreign->fresh()->name);
    }

    public function test_a_grade_from_another_school_cannot_be_attached(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreignClass = SchoolClass::create(['school_id' => $other->id, 'name' => 'Their Grade', 'level' => 9]);

        $this->actingAs($this->manager())->post(route('subjects.store'), [
            'name' => 'Physics',
            'code' => 'PHY101',
            'class_ids' => [$foreignClass->id],
        ]);

        // The id does not resolve through the tenant-scoped query, so nothing
        // is attached rather than a cross-school link being made.
        $this->assertSame(0, Subject::where('code', 'PHY101')->firstOrFail()->schoolClasses()->count());
    }

    /* ---------------------------------------------------------- the sidebar */

    public function test_the_sidebar_groups_collapse_and_the_current_one_is_open(): void
    {
        $html = $this->actingAs($this->manager())->get(route('subjects.index'))->assertOk()->getContent();

        /*
         | The group holding the page you are on starts open; collapsing what
         | someone just clicked would lose their place. Everything else starts
         | shut, which is the point - the menu was long enough to scroll.
         */
        $this->assertStringContainsString('x-data="{ open: true }"', $html);
        $this->assertStringContainsString('x-data="{ open: false }"', $html);

        // Worked out server-side, so the right group is open in the first
        // painted frame and for anyone with JavaScript off.
        $this->assertStringContainsString('aria-expanded', $html);
        $this->assertStringContainsString('x-collapse', $html);
    }
}
