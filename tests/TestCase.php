<?php

namespace Tests;

use App\Actions\ProvisionSchoolRoles;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Permissions;
use App\Support\SchoolContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures are built outside a request, where SchoolContext is
        // deliberately unresolved. Clearing it between tests stops one test's
        // tenant leaking into the next.
        app(SchoolContext::class)->forget();
    }

    /** Seed the permission catalogue once for tests that need real permissions. */
    protected function seedPermissions(): void
    {
        if (Permission::count() === 0) {
            $this->seed(PermissionSeeder::class);
        }
    }

    /** Create a school with its full set of default roles. */
    protected function createSchool(array $attributes = []): School
    {
        $this->seedPermissions();

        $school = School::factory()->create($attributes);

        app(ProvisionSchoolRoles::class)->handle($school);

        return $school;
    }

    /**
     * Create a user in a school holding exactly the permissions listed.
     *
     * @param  array<int, string>  $permissions
     */
    protected function userFor(School $school, array $permissions = [], array $attributes = []): User
    {
        $this->seedPermissions();

        $user = User::factory()->create(['school_id' => $school->id] + $attributes);

        $role = Role::create([
            'school_id' => $school->id,
            'name' => 'Test Role '.$user->id,
            'slug' => 'test-role-'.$user->id,
            'is_system' => false,
        ]);

        $role->permissions()->sync(Permission::whereIn('slug', $permissions)->pluck('id'));

        $user->roles()->attach($role);

        return $user->fresh();
    }

    /** A user holding every permission in their school. */
    protected function administratorFor(School $school): User
    {
        return $this->userFor($school, Permissions::slugs());
    }

    /** A platform-level super administrator, belonging to no school. */
    protected function superAdministrator(): User
    {
        $this->seedPermissions();

        $role = Role::firstOrCreate(
            ['school_id' => null, 'slug' => User::SUPER_ADMINISTRATOR],
            ['name' => 'Super Administrator', 'is_system' => true],
        );

        $user = User::factory()->create(['school_id' => null]);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
