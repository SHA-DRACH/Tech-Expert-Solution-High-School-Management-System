<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Delegating a job without delegating the school.
 *
 * Before this, anyone trusted with users.update could give any account -
 * their own included - the School Administrator role, and anyone with
 * roles.manage could add any permission to a role they held.
 */
class DelegationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->createSchool();
        app(SchoolContext::class)->setSchool($this->school);
    }

    protected function role(string $slug): Role
    {
        return Role::inCurrentSchool()->where('slug', $slug)->firstOrFail();
    }

    protected function holding(string $slug): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id, 'status' => 'active']);
        $user->roles()->sync([$this->role($slug)->id]);

        return $user->fresh();
    }

    public function test_every_school_starts_with_an_admissions_head_and_an_hr_officer(): void
    {
        $this->assertTrue($this->role('admissions-head')->permissions->contains('slug', 'admissions.approve'));
        $this->assertTrue($this->role('hr-officer')->permissions->contains('slug', 'users.create'));
        $this->assertFalse($this->role('hr-officer')->permissions->contains('slug', 'roles.manage'));
    }

    /** The exploit itself. */
    public function test_an_hr_officer_cannot_make_themselves_administrator(): void
    {
        $hr = $this->holding('hr-officer');

        $this->actingAs($hr)->put(route('users.update', $hr), [
            'name' => $hr->name,
            'email' => $hr->email,
            'roles' => [$this->role('school-administrator')->id],
        ])->assertSessionHasErrors('roles');

        $this->assertFalse($hr->fresh()->hasRole('school-administrator'));
    }

    public function test_an_hr_officer_cannot_create_an_administrator_account(): void
    {
        $this->actingAs($this->holding('hr-officer'))->post(route('users.store'), [
            'name' => 'New Admin',
            'email' => 'new.admin@example.test',
            'password' => 'a-long-password-123',
            'password_confirmation' => 'a-long-password-123',
            'role_id' => $this->role('school-administrator')->id,
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'new.admin@example.test']);
    }

    /** The delegation the school actually wants still works. */
    public function test_an_hr_officer_can_give_a_new_teacher_a_login(): void
    {
        $teacher = Teacher::create([
            'school_id' => $this->school->id, 'staff_number' => 'T-9',
            'first_name' => 'Paul', 'last_name' => 'Toe', 'status' => 'active',
        ]);

        $this->actingAs($this->holding('hr-officer'))
            ->post(route('teachers.login.store', $teacher), [
                'email' => 'paul.toe@example.test',
                'password' => 'a-long-password-123',
                'password_confirmation' => 'a-long-password-123',
                'role_id' => $this->role('teacher')->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'paul.toe@example.test')->firstOrFail()->hasRole('teacher'));
    }

    public function test_an_hr_officer_cannot_edit_an_account_that_outranks_them(): void
    {
        $principal = $this->holding('school-administrator');

        $this->actingAs($this->holding('hr-officer'))->put(route('users.update', $principal), [
            'name' => 'Renamed',
            'email' => $principal->email,
            'password' => 'taken-over-12345',
            'password_confirmation' => 'taken-over-12345',
            'roles' => [$this->role('teacher')->id],
        ])->assertForbidden();

        $this->assertTrue($principal->fresh()->hasRole('school-administrator'));
    }

    public function test_an_hr_officer_cannot_suspend_an_account_that_outranks_them(): void
    {
        $principal = $this->holding('school-administrator');

        $this->assertFalse($this->holding('hr-officer')->can('suspend', $principal));
    }

    public function test_a_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $manager = $this->userFor($this->school, ['roles.view', 'roles.manage', 'dashboard.view']);
        $role = $manager->roles->first();

        $this->actingAs($manager)->put(route('roles.update', $role), [
            'name' => $role->name,
            'permissions' => ['dashboard.view', 'roles.view', 'roles.manage', 'settings.manage'],
        ])->assertSessionHasErrors('permissions');

        $this->assertFalse($manager->fresh()->hasPermission('settings.manage'));
    }

    public function test_the_administrator_can_still_grant_anything(): void
    {
        $admin = $this->holding('school-administrator');
        $hr = $this->holding('hr-officer');

        $this->actingAs($admin)->put(route('users.update', $hr), [
            'name' => $hr->name,
            'email' => $hr->email,
            'roles' => [$this->role('hr-officer')->id, $this->role('accountant')->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($hr->fresh()->hasRole('accountant'));
    }
}
