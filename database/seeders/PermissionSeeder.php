<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Syncs the permissions table with the catalogue. Safe to re-run: existing rows
 * are updated in place so role assignments survive.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::CATALOGUE as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'group_name' => $group],
                );
            }
        }

        // Drop permissions that are no longer part of the catalogue.
        Permission::whereNotIn('slug', Permissions::slugs())->delete();
    }
}
