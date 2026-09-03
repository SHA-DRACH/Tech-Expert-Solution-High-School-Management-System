<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class and section management (spec section 28).
 *
 * "Classes and sections must be configurable" — creating them worked; editing
 * and removing them did not have a screen. The controller methods and routes
 * existed but nothing in the interface reached them, so an administrator could
 * make a typo in a grade name and had no way to correct it.
 */
class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);
    }

    protected function manager()
    {
        return $this->userFor($this->school, ['academics.view', 'academics.manage']);
    }

    protected function grade(string $name = 'Grade 10', int $level = 10): SchoolClass
    {
        return SchoolClass::create([
            'school_id' => $this->school->id, 'name' => $name, 'level' => $level, 'stage' => 'Senior High',
        ]);
    }

    /* ---------------------------------------------------------- creating */

    public function test_the_grades_in_the_spec_can_all_be_created(): void
    {
        $manager = $this->manager();

        foreach (range(7, 12) as $level) {
            $this->actingAs($manager)
                ->post(route('academics.classes.store'), [
                    'name' => "Grade {$level}",
                    'level' => $level,
                    'stage' => $level >= 10 ? 'Senior High' : 'Junior High',
                ])
                ->assertRedirect();
        }

        $this->assertSame(
            ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'],
            SchoolClass::orderBy('level')->pluck('name')->all(),
        );
    }

    public function test_a_grade_takes_several_sections(): void
    {
        $grade = $this->grade();
        $manager = $this->manager();

        foreach (['A', 'B', 'C'] as $name) {
            $this->actingAs($manager)
                ->post(route('academics.sections.store'), [
                    'school_class_id' => $grade->id,
                    'name' => $name,
                ])
                ->assertRedirect();
        }

        // Grade 10 ├── 10A ├── 10B └── 10C
        $this->assertSame(
            ['Grade 10A', 'Grade 10B', 'Grade 10C'],
            Section::with('schoolClass')->get()->map->full_name->sort()->values()->all(),
        );
    }

    /* ----------------------------------------------------------- editing */

    public function test_a_grade_can_be_renamed(): void
    {
        $grade = $this->grade('Grde 10');

        $this->actingAs($this->manager())
            ->put(route('academics.classes.update', $grade), [
                'name' => 'Grade 10', 'level' => 10, 'stage' => 'Senior High',
            ])
            ->assertRedirect();

        $this->assertSame('Grade 10', $grade->fresh()->name);
    }

    public function test_a_section_can_be_edited_and_given_a_class_teacher(): void
    {
        $grade = $this->grade();

        $section = Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $grade->id, 'name' => 'A',
        ]);

        $teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-001',
            'first_name' => 'Grace', 'last_name' => 'Kollie', 'status' => 'active',
        ]);

        $this->actingAs($this->manager())
            ->put(route('academics.sections.update', $section), [
                'school_class_id' => $grade->id,
                'name' => 'A',
                'room' => 'Room 4',
                'capacity' => 35,
                'class_teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        $section->refresh();

        $this->assertSame('Room 4', $section->room);
        $this->assertSame(35, $section->capacity);
        $this->assertSame($teacher->id, $section->class_teacher_id);
    }

    public function test_the_screen_offers_editing_and_removal(): void
    {
        $grade = $this->grade();

        Section::create(['school_id' => $this->school->id, 'school_class_id' => $grade->id, 'name' => 'A']);

        $html = $this->actingAs($this->manager())->get(route('academics.index'))->assertOk()->getContent();

        // The routes existed; nothing in the interface reached them.
        foreach ([
            route('academics.classes.update', $grade),
            route('academics.classes.destroy', $grade),
            route('academics.sections.update', Section::first()),
            route('academics.sections.destroy', Section::first()),
        ] as $action) {
            $this->assertStringContainsString($action, $html);
        }
    }

    public function test_a_viewer_is_not_offered_the_editing_controls(): void
    {
        $grade = $this->grade();

        $html = $this->actingAs($this->userFor($this->school, ['academics.view']))
            ->get(route('academics.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('academics.classes.update', $grade), $html);
    }

    /* ---------------------------------------------------------- removing */

    public function test_a_grade_holding_enrolments_is_not_archived(): void
    {
        $grade = $this->grade();
        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $grade->id, 'name' => 'A']);

        $student = Student::create([
            'school_id' => $this->school->id, 'student_number' => 'S-1',
            'first_name' => 'Ada', 'last_name' => 'Doe', 'status' => 'active',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id, 'student_id' => $student->id,
            'academic_year_id' => $this->year->id, 'school_class_id' => $grade->id,
            'section_id' => $section->id, 'status' => 'active',
        ]);

        // Last year's report cards name the class a child was in.
        $this->actingAs($this->manager())
            ->delete(route('academics.classes.destroy', $grade))
            ->assertSessionHasErrors('structure');

        $this->assertSame(1, SchoolClass::count());
    }

    public function test_a_grade_with_sections_is_not_archived_until_they_are(): void
    {
        $grade = $this->grade();
        Section::create(['school_id' => $this->school->id, 'school_class_id' => $grade->id, 'name' => 'A']);

        $this->actingAs($this->manager())
            ->delete(route('academics.classes.destroy', $grade))
            ->assertSessionHasErrors('structure');

        $this->assertSame(1, SchoolClass::count());
    }

    public function test_an_empty_grade_and_section_can_be_archived(): void
    {
        $grade = $this->grade();
        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $grade->id, 'name' => 'A']);

        $manager = $this->manager();

        $this->actingAs($manager)
            ->delete(route('academics.sections.destroy', $section))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Section::count());

        $this->actingAs($manager)
            ->delete(route('academics.classes.destroy', $grade))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, SchoolClass::count());
    }

    /* ------------------------------------------------------ authorization */

    public function test_editing_needs_the_manage_permission(): void
    {
        $grade = $this->grade();

        $this->actingAs($this->userFor($this->school, ['academics.view']))
            ->put(route('academics.classes.update', $grade), ['name' => 'Hijacked', 'level' => 10])
            ->assertForbidden();
    }

    public function test_a_class_from_another_school_cannot_be_edited(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);

        $foreign = SchoolClass::create(['school_id' => $other->id, 'name' => 'Their Grade', 'level' => 9]);

        $this->actingAs($this->manager())
            ->put(route('academics.classes.update', $foreign), ['name' => 'Hijacked', 'level' => 9])
            ->assertNotFound();

        $this->assertSame('Their Grade', $foreign->fresh()->name);
    }

    public function test_a_section_cannot_be_moved_to_another_schools_class(): void
    {
        $other = $this->createSchool(['name' => 'Another School']);
        $foreignClass = SchoolClass::create(['school_id' => $other->id, 'name' => 'Their Grade', 'level' => 9]);

        $grade = $this->grade();
        $section = Section::create(['school_id' => $this->school->id, 'school_class_id' => $grade->id, 'name' => 'A']);

        // The id is re-resolved through the tenant-scoped query, so it does not
        // resolve and the move is refused rather than silently made.
        $this->actingAs($this->manager())
            ->put(route('academics.sections.update', $section), [
                'school_class_id' => $foreignClass->id,
                'name' => 'A',
            ])
            ->assertNotFound();

        $this->assertSame($grade->id, $section->fresh()->school_class_id);
    }
}
