<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_the_permission_is_refused_the_route(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view']);

        $this->actingAs($user)->get(route('students.index'))->assertForbidden();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
    }

    public function test_granting_a_permission_opens_exactly_that_route(): void
    {
        $school = $this->createSchool();
        $user = $this->userFor($school, ['dashboard.view', 'students.view']);

        $this->actingAs($user)->get(route('students.index'))->assertOk();

        // Viewing does not imply creating.
        $this->actingAs($user)->get(route('students.create'))->assertForbidden();
    }

    public function test_the_sidebar_only_offers_what_the_user_may_open(): void
    {
        $school = $this->createSchool();

        $limited = $this->userFor($school, ['dashboard.view', 'students.view']);

        $this->actingAs($limited)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Students')
            ->assertDontSee('Roles &amp; permissions')
            ->assertDontSee('Audit trail');
    }

    public function test_a_super_administrator_bypasses_permission_checks(): void
    {
        $this->createSchool();

        $this->actingAs($this->superAdministrator())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_a_school_administrator_can_change_what_a_role_may_do(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $teacher = Role::where('school_id', $school->id)->where('slug', 'teacher')->firstOrFail();

        $this->actingAs($administrator)
            ->put(route('roles.update', $teacher), [
                'name' => 'Teacher',
                'description' => 'Updated by test',
                'permissions' => ['dashboard.view', 'students.view', 'attendance.record'],
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'students.view', 'attendance.record'],
            $teacher->fresh()->permissions->pluck('slug')->all(),
        );
    }

    public function test_a_permission_slug_outside_the_catalogue_is_rejected(): void
    {
        $school = $this->createSchool();

        $this->actingAs($this->administratorFor($school))
            ->post(route('roles.store'), [
                'name' => 'Invented Role',
                'permissions' => ['everything.everywhere'],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Invented Role']);
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $school = $this->createSchool();

        $registrar = Role::where('school_id', $school->id)->where('slug', 'registrar')->firstOrFail();

        $this->actingAs($this->administratorFor($school))
            ->delete(route('roles.destroy', $registrar))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $registrar->id]);
    }

    public function test_a_role_still_in_use_cannot_be_deleted(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $role = Role::create([
            'school_id' => $school->id,
            'name' => 'Temporary',
            'slug' => 'temporary',
            'is_system' => false,
        ]);

        $holder = $this->userFor($school, []);
        $holder->roles()->attach($role);

        $this->actingAs($administrator)
            ->delete(route('roles.destroy', $role))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $school = $this->createSchool();

        $role = Role::create([
            'school_id' => $school->id,
            'name' => 'Disposable',
            'slug' => 'disposable',
            'is_system' => false,
        ]);

        $this->actingAs($this->administratorFor($school))
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_nobody_can_suspend_their_own_account(): void
    {
        $school = $this->createSchool();
        $administrator = $this->administratorFor($school);

        $this->actingAs($administrator)
            ->patch(route('users.status', $administrator))
            ->assertForbidden();

        $this->assertSame('active', $administrator->fresh()->status);
    }

    public function test_a_student_may_read_their_own_record_without_the_students_permission(): void
    {
        $school = $this->createSchool();

        $account = $this->userFor($school, []);
        $student = Student::factory()->create(['school_id' => $school->id, 'user_id' => $account->id]);

        $this->assertTrue($account->fresh()->can('view', $student));
    }
}
