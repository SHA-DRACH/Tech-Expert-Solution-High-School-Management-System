<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The registrar's desk.
 *
 * The hub itself invents no authority, so most of what is worth testing here
 * is the boundary: that a link between a parent and a child carries the terms
 * it was given, that those terms cannot be set across a tenant, and that the
 * printed record says only what the school actually holds.
 */
class RegistrarTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Student $student;

    protected AcademicYear $year;

    protected SchoolClass $class;

    protected Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();

        app(SchoolContext::class)->setSchool($this->school);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026 / 2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'school_id' => $this->school->id, 'name' => 'Grade 9', 'level' => 9,
        ]);

        $this->section = Section::create([
            'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'name' => 'A',
        ]);

        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
            'student_number' => 'STU-0001',
            'status' => 'active',
        ]);
    }

    protected function registrar(): \App\Models\User
    {
        return $this->userFor($this->school, [
            'dashboard.view', 'students.view', 'students.update',
            'guardians.view', 'reportcards.view',
        ]);
    }

    protected function guardian(array $overrides = []): Guardian
    {
        return Guardian::create(array_merge([
            'school_id' => $this->school->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+231770000000',
        ], $overrides));
    }

    protected function place(Student $student): Enrollment
    {
        return Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The hub                                                             */
    /* ------------------------------------------------------------------ */

    public function test_the_hub_shows_each_class_and_how_many_students_are_in_it(): void
    {
        $this->place($this->student);

        $this->actingAs($this->registrar())->get(route('registrar.index'))
            ->assertOk()
            ->assertSee('Grade 9')
            ->assertSee('Students on roll');
    }

    /**
     * A roll that counts children who have left flatters the number, and
     * seating gets planned from it.
     */
    public function test_the_class_count_excludes_students_who_have_left(): void
    {
        $this->place($this->student);

        $gone = Student::factory()->create([
            'school_id' => $this->school->id, 'status' => 'withdrawn',
        ]);

        $this->place($gone);

        $response = $this->actingAs($this->registrar())->get(route('registrar.index'));

        $response->assertOk();

        $classes = $response->viewData('classes');

        $this->assertSame(1, (int) $classes->firstWhere('id', $this->class->id)->students_count);
    }

    public function test_the_hub_surfaces_students_with_nobody_to_telephone(): void
    {
        $response = $this->actingAs($this->registrar())->get(route('registrar.index'));

        $this->assertSame(1, $response->viewData('withoutGuardian'));

        $this->student->guardians()->attach($this->guardian()->id, ['relationship' => 'Father']);

        $this->assertSame(
            0,
            $this->actingAs($this->registrar())->get(route('registrar.index'))->viewData('withoutGuardian')
        );
    }

    public function test_the_hub_needs_permission_to_see_students(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('registrar.index'))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* The printed record                                                  */
    /* ------------------------------------------------------------------ */

    public function test_the_student_record_prints_with_a_verification_code(): void
    {
        $this->place($this->student);
        $this->student->guardians()->attach($this->guardian()->id, ['relationship' => 'Father']);

        $this->actingAs($this->registrar())->get(route('students.record', $this->student))
            ->assertOk()
            ->assertSee('Mary Doe')
            ->assertSee('STU-0001')
            ->assertSee('Grade 9')
            ->assertSee('John Doe')
            ->assertSee('printable', false)
            ->assertSee('<svg', false);
    }

    public function test_printing_a_record_needs_permission_to_view_the_student(): void
    {
        $this->actingAs($this->userFor($this->school, ['dashboard.view']))
            ->get(route('students.record', $this->student))
            ->assertForbidden();
    }

    public function test_a_student_record_from_another_school_cannot_be_printed(): void
    {
        $other = $this->createSchool();

        $foreign = Student::factory()->create(['school_id' => $other->id]);

        $this->actingAs($this->registrar())
            ->get(route('students.record', $foreign))
            ->assertNotFound();
    }

    /** The check behind the code confirms the number, and nothing more. */
    public function test_a_student_number_can_be_verified_without_revealing_the_child(): void
    {
        $school = School::factory()->create(['domain' => 'registrar-test.test']);

        app(SchoolContext::class)->setSchool($school);

        Student::factory()->create([
            'school_id' => $school->id,
            'first_name' => 'Mary',
            'last_name' => 'Doe',
            'student_number' => 'STU-9001',
            'status' => 'active',
        ]);

        $this->get('http://registrar-test.test/verify/student-record/STU-9001')
            ->assertOk()
            ->assertSee('STU-9001')
            ->assertSee('Mary D.')
            ->assertDontSee('Mary Doe');
    }

    /* ------------------------------------------------------------------ */
    /* Linking a parent to a child                                         */
    /* ------------------------------------------------------------------ */

    public function test_a_parent_can_be_linked_to_a_student(): void
    {
        $guardian = $this->guardian();

        $this->actingAs($this->registrar())
            ->post(route('students.guardians.attach', $this->student), [
                'guardian_id' => $guardian->id,
                'relationship' => 'Father',
                'is_primary' => 1,
                'can_view_academics' => 1,
                'can_view_finance' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guardian_student', [
            'student_id' => $this->student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'Father',
            'is_primary' => true,
        ]);
    }

    /**
     * The terms of the link are what decide whether a parent can read a child's
     * results and fees, so an unticked box has to mean no.
     */
    public function test_an_unticked_box_denies_the_parent_that_view(): void
    {
        $guardian = $this->guardian();

        $this->actingAs($this->registrar())
            ->post(route('students.guardians.attach', $this->student), [
                'guardian_id' => $guardian->id,
                'relationship' => 'Aunt',
                // Neither box ticked.
            ]);

        $pivot = $this->student->guardians()->first()->pivot;

        $this->assertFalse((bool) $pivot->can_view_academics);
        $this->assertFalse((bool) $pivot->can_view_finance);
        $this->assertFalse((bool) $pivot->is_primary);
    }

    /** "Who do we ring first?" needs exactly one answer. */
    public function test_naming_a_new_primary_contact_demotes_the_old_one(): void
    {
        $first = $this->guardian(['first_name' => 'John']);
        $second = $this->guardian(['first_name' => 'Grace']);

        $registrar = $this->registrar();

        $this->actingAs($registrar)->post(route('students.guardians.attach', $this->student), [
            'guardian_id' => $first->id, 'relationship' => 'Father', 'is_primary' => 1,
        ]);

        $this->actingAs($registrar)->post(route('students.guardians.attach', $this->student), [
            'guardian_id' => $second->id, 'relationship' => 'Mother', 'is_primary' => 1,
        ]);

        $primaries = $this->student->guardians()->get()
            ->filter(fn ($g) => (bool) $g->pivot->is_primary);

        $this->assertCount(1, $primaries);
        $this->assertSame($second->id, $primaries->first()->id);
    }

    public function test_a_link_can_be_removed(): void
    {
        $guardian = $this->guardian();

        $this->student->guardians()->attach($guardian->id, ['relationship' => 'Father']);

        $this->actingAs($this->registrar())
            ->delete(route('students.guardians.detach', [$this->student, $guardian]))
            ->assertRedirect();

        $this->assertDatabaseMissing('guardian_student', [
            'student_id' => $this->student->id,
            'guardian_id' => $guardian->id,
        ]);
    }

    public function test_the_terms_of_an_existing_link_can_be_changed(): void
    {
        $guardian = $this->guardian();

        $this->student->guardians()->attach($guardian->id, [
            'relationship' => 'Father', 'can_view_finance' => true,
        ]);

        $this->actingAs($this->registrar())
            ->put(route('students.guardians.update', [$this->student, $guardian]), [
                'relationship' => 'Stepfather',
                'can_view_academics' => 1,
                // Finance deliberately left off.
            ])
            ->assertRedirect();

        $pivot = $this->student->guardians()->first()->pivot;

        $this->assertSame('Stepfather', $pivot->relationship);
        $this->assertTrue((bool) $pivot->can_view_academics);
        $this->assertFalse((bool) $pivot->can_view_finance);
    }

    public function test_linking_a_parent_needs_permission_to_edit_the_student(): void
    {
        $guardian = $this->guardian();

        $this->actingAs($this->userFor($this->school, ['students.view']))
            ->post(route('students.guardians.attach', $this->student), [
                'guardian_id' => $guardian->id,
                'relationship' => 'Father',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('guardian_student', [
            'student_id' => $this->student->id,
            'guardian_id' => $guardian->id,
        ]);
    }

    /**
     * The id came from a form, so it is not to be trusted (section 59). A
     * guardian from another school must not become reachable by guessing.
     */
    public function test_a_guardian_from_another_school_cannot_be_linked(): void
    {
        $other = $this->createSchool();

        $foreign = Guardian::withoutGlobalScopes()->create([
            'school_id' => $other->id,
            'first_name' => 'Foreign',
            'last_name' => 'Parent',
        ]);

        $this->actingAs($this->registrar())
            ->post(route('students.guardians.attach', $this->student), [
                'guardian_id' => $foreign->id,
                'relationship' => 'Father',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('guardian_student', [
            'student_id' => $this->student->id,
            'guardian_id' => $foreign->id,
        ]);
    }

    public function test_linking_a_parent_is_audited(): void
    {
        $guardian = $this->guardian();

        $this->actingAs($this->registrar())
            ->post(route('students.guardians.attach', $this->student), [
                'guardian_id' => $guardian->id,
                'relationship' => 'Father',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $this->school->id,
            'module' => 'Students',
            'action' => 'guardian_linked',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Finding students by class                                           */
    /* ------------------------------------------------------------------ */

    public function test_the_student_list_can_be_filtered_to_one_class(): void
    {
        $this->place($this->student);

        $elsewhere = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Peter',
            'last_name' => 'Kollie',
            'status' => 'active',
        ]);

        $this->actingAs($this->registrar())
            ->get(route('students.index', ['class' => $this->class->id]))
            ->assertOk()
            ->assertSee('Mary Doe')
            ->assertDontSee($elsewhere->student_number);
    }

    /**
     * A child in Grade 9 last year and Grade 10 now belongs under one of them,
     * not both.
     */
    public function test_the_class_filter_uses_this_years_placement(): void
    {
        $lastYear = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2025 / 2026',
            'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30',
        ]);

        Enrollment::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $lastYear->id,
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
        ]);

        // Placed in that class last year only, so this year's filter misses it.
        $this->actingAs($this->registrar())
            ->get(route('students.index', ['class' => $this->class->id]))
            ->assertOk()
            ->assertDontSee('STU-0001');
    }
}
