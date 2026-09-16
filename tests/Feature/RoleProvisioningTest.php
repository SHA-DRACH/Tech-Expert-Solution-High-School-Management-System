<?php

namespace Tests\Feature;

use App\Actions\ProvisionSchoolRoles;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What happens to a school's roles when the platform gains a capability.
 *
 * This guards a failure with no error message. A permission added to the
 * catalogue used to reach only schools created afterwards; every existing
 * school administrator was left without it, and the symptom was a module
 * quietly missing from the sidebar rather than anything that looked broken.
 */
class RoleProvisioningTest extends TestCase
{
    use RefreshDatabase;

    /** A permission the catalogue does not know about, as a later release would add. */
    protected function addPermissionToTheCatalogue(string $slug): Permission
    {
        return Permission::create([
            'slug' => $slug,
            'name' => 'A capability added later',
            'group_name' => 'Testing',
        ]);
    }

    public function test_a_role_defined_as_full_access_gains_permissions_added_later(): void
    {
        $school = $this->createSchool();

        $admin = Role::where('school_id', $school->id)->where('slug', 'school-administrator')->firstOrFail();

        $this->assertFalse($admin->permissions->contains('slug', 'widgets.manage'));

        $this->addPermissionToTheCatalogue('widgets.manage');

        app(ProvisionSchoolRoles::class)->handle($school);

        $this->assertTrue(
            $admin->fresh('permissions')->permissions->contains('slug', 'widgets.manage'),
            'A role meaning "everything" has to still mean everything after the catalogue grows.'
        );
    }

    /**
     * The other half of the bargain: a role with a named list is a decision the
     * school made, and a platform update must not quietly widen it.
     */
    public function test_a_role_with_a_named_list_is_not_widened_by_a_later_permission(): void
    {
        $school = $this->createSchool();

        $teacher = Role::where('school_id', $school->id)->where('slug', 'teacher')->firstOrFail();

        $before = $teacher->permissions->count();

        $this->addPermissionToTheCatalogue('widgets.manage');

        app(ProvisionSchoolRoles::class)->handle($school);

        $teacher->refresh()->load('permissions');

        $this->assertSame($before, $teacher->permissions->count());
        $this->assertFalse($teacher->permissions->contains('slug', 'widgets.manage'));
    }

    /** A school's own edits to a customised role survive a platform update. */
    public function test_a_customised_role_keeps_its_permissions(): void
    {
        $school = $this->createSchool();

        $teacher = Role::where('school_id', $school->id)->where('slug', 'teacher')->firstOrFail();

        $teacher->permissions()->sync(Permission::where('slug', 'dashboard.view')->pluck('id'));

        app(ProvisionSchoolRoles::class)->handle($school);

        $this->assertSame(1, $teacher->fresh('permissions')->permissions->count());
    }

    /**
     * Every permission in the catalogue must actually exist as a row, or a
     * Gate check for it fails for everyone including the administrator.
     */
    public function test_every_catalogued_permission_is_seeded(): void
    {
        $this->seedPermissions();

        $missing = array_diff(Permissions::slugs(), Permission::pluck('slug')->all());

        $this->assertSame([], array_values($missing),
            'These permissions are in the catalogue but have no row: '.implode(', ', $missing));
    }
}
