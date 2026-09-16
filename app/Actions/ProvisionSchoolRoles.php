<?php

namespace App\Actions;

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;

/**
 * Gives a school its starting set of roles.
 *
 * Run when a school is onboarded, and safe to re-run afterwards: it never
 * touches a role's permissions once the school has customised them, so an
 * administrator's edits are not overwritten by a later platform update.
 *
 * The one exception is a role defined as holding *everything*. That definition
 * is a statement about the catalogue rather than a list frozen on the day the
 * school was created, so a permission added to the platform later has to reach
 * it. Without this, adding a capability silently left every existing school
 * administrator unable to use it, with no error to explain why - the module was
 * simply not in their sidebar.
 */
class ProvisionSchoolRoles
{
    public function handle(School $school, bool $resetPermissions = false): void
    {
        $permissionIds = Permission::pluck('id', 'slug');

        DB::transaction(function () use ($school, $permissionIds, $resetPermissions) {
            foreach (Permissions::defaultRoles() as $slug => $definition) {
                $role = Role::firstOrNew(['school_id' => $school->id, 'slug' => $slug]);

                $isNew = ! $role->exists;

                $role->fill([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ])->save();

                $holdsEverything = in_array(Permissions::ALL, $definition['permissions'], true);

                if ($isNew || $resetPermissions) {
                    $role->permissions()->sync($this->resolve($definition['permissions'], $permissionIds));
                } elseif ($holdsEverything) {
                    // Additive: catches up on anything new in the catalogue
                    // without disturbing the rest of the school's roles.
                    $role->permissions()->syncWithoutDetaching($permissionIds->values()->all());
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, int>
     */
    protected function resolve(array $slugs, $permissionIds): array
    {
        if (in_array(Permissions::ALL, $slugs, true)) {
            return $permissionIds->values()->all();
        }

        return $permissionIds->only($slugs)->values()->all();
    }
}
