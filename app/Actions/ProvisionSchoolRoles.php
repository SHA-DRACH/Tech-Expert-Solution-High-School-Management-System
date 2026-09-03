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

                if ($isNew || $resetPermissions) {
                    $role->permissions()->sync($this->resolve($definition['permissions'], $permissionIds));
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
