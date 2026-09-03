<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\User;
use App\Services\StudentAccess;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec section 24: what a student account may open is data an administrator
 * edits, never something hard-coded.
 */
class StudentPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function studentAccount(\App\Models\School $school): array
    {
        $user = $this->userFor($school, []);

        $user->roles()->detach();
        $user->roles()->attach(
            Role::where('school_id', $school->id)->where('slug', 'student')->firstOrFail()
        );

        $student = Student::factory()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        return [$user->fresh(), $student];
    }

    public function test_the_catalogue_defaults_apply_when_nothing_is_configured(): void
    {
        $school = $this->createSchool();
        [, $student] = $this->studentAccount($school);

        app(SchoolContext::class)->setSchool($school);

        $access = app(StudentAccess::class);

        // Straight from the catalogue: viewing grades is on, sending messages off.
        $this->assertTrue($access->allows($student, 'view_grades'));
        $this->assertFalse($access->allows($student, 'send_messages'));
    }

    public function test_a_school_default_overrides_the_catalogue(): void
    {
        $school = $this->createSchool();
        [, $student] = $this->studentAccount($school);

        StudentPermission::create([
            'school_id' => $school->id,
            'student_id' => null,
            'ability' => 'view_grades',
            'allowed' => false,
        ]);

        app(SchoolContext::class)->setSchool($school);

        $this->assertFalse(app(StudentAccess::class)->allows($student, 'view_grades'));
    }

    public function test_a_student_override_beats_the_school_default(): void
    {
        $school = $this->createSchool();
        [, $student] = $this->studentAccount($school);

        StudentPermission::create([
            'school_id' => $school->id,
            'student_id' => null,
            'ability' => 'view_fees',
            'allowed' => false,
        ]);

        StudentPermission::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'ability' => 'view_fees',
            'allowed' => true,
        ]);

        app(SchoolContext::class)->setSchool($school);

        $this->assertTrue(app(StudentAccess::class)->allows($student, 'view_fees'));
    }

    public function test_turning_an_ability_off_closes_that_part_of_the_portal(): void
    {
        $school = $this->createSchool();
        [$user, $student] = $this->studentAccount($school);

        // Open by default.
        $this->actingAs($user)->get(route('student.grades'))->assertOk();

        StudentPermission::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'ability' => 'view_grades',
            'allowed' => false,
        ]);

        $this->actingAs($user)->get(route('student.grades'))->assertForbidden();
    }

    public function test_a_closed_section_is_not_offered_in_the_portal_navigation(): void
    {
        $school = $this->createSchool();
        [$user, $student] = $this->studentAccount($school);

        $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->assertSee('Timetable');

        StudentPermission::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'ability' => 'view_timetable',
            'allowed' => false,
        ]);

        $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->assertDontSee('Timetable');
    }

    public function test_an_administrator_can_change_the_school_wide_defaults(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $this->actingAs($administrator)
            ->put(route('students.permissions.defaults'), [
                'abilities' => ['view_grades' => '1', 'view_attendance' => '1'],
            ])
            ->assertRedirect();

        // Anything not ticked is switched off.
        $this->assertDatabaseHas('student_permissions', [
            'school_id' => $school->id,
            'student_id' => null,
            'ability' => 'view_grades',
            'allowed' => 1,
        ]);

        $this->assertDatabaseHas('student_permissions', [
            'school_id' => $school->id,
            'student_id' => null,
            'ability' => 'send_messages',
            'allowed' => 0,
        ]);
    }

    public function test_returning_a_student_to_the_school_default_removes_their_override(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);
        [, $student] = $this->studentAccount($school);

        StudentPermission::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'ability' => 'view_fees',
            'allowed' => true,
        ]);

        $this->actingAs($administrator)
            ->put(route('students.permissions.update', $student), [
                'use_default' => ['view_fees' => '1'],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('student_permissions', [
            'student_id' => $student->id,
            'ability' => 'view_fees',
        ]);
    }

    public function test_permissions_of_a_student_in_another_school_cannot_be_changed(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $theirStudent = Student::factory()->create(['school_id' => $schoolB->id]);

        $this->actingAs($this->administratorFor($schoolA))
            ->put(route('students.permissions.update', $theirStudent), [
                'abilities' => ['view_fees' => '1'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('student_permissions', [
            'student_id' => $theirStudent->id,
        ]);
    }
}
