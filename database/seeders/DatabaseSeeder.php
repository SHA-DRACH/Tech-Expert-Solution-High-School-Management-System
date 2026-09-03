<?php

namespace Database\Seeders;

use App\Actions\ProvisionSchoolRoles;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $this->platformAdministrator();

        // Grace Foundation Institution is the first tenant on the platform, not
        // a special case in the code: it is provisioned exactly the way any
        // other school will be.
        $this->call(GraceFoundationSeeder::class);

        // Every school starts with a complete public website rather than
        // blank pages waiting for someone to notice them.
        $this->call(WebsiteContentSeeder::class);
    }

    protected function platformAdministrator(): void
    {
        $role = Role::firstOrCreate(
            ['school_id' => null, 'slug' => User::SUPER_ADMINISTRATOR],
            [
                'name' => 'Super Administrator',
                'description' => 'Platform-wide administration across every school.',
                'is_system' => true,
            ],
        );

        $user = User::firstOrCreate(
            ['email' => 'platform@gsms.test'],
            [
                'school_id' => null,
                'name' => 'GSMS Platform Administrator',
                'password' => Hash::make('ChangeMe123!'),
                'status' => 'active',
            ],
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
