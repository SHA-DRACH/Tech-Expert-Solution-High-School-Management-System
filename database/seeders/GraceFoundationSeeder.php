<?php

namespace Database\Seeders;

use App\Actions\ProvisionSchoolRoles;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Onboards the pilot school. This seeder only supplies data; it uses the same
 * provisioning action any future school will go through.
 */
class GraceFoundationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'grace-foundation-institution'],
            [
                'name' => 'Grace Foundation Institution',
                'short_name' => 'Grace Foundation',
                'motto' => 'Inspiring excellence, character, and service.',
                'email' => 'info@gracefoundation.edu.lr',
                'phone' => '+231 77 000 0000',
                'address' => 'Monrovia, Montserrado County, Liberia',
                'primary_color' => '#0B3C91',
                'secondary_color' => '#D4AF37',
                'timezone' => 'Africa/Monrovia',
                'student_number_prefix' => 'GFI',
                'is_active' => true,
            ],
        );

        app(ProvisionSchoolRoles::class)->handle($school);

        app(SchoolContext::class)->for($school, function (School $school) {
            $this->settings($school);
            $this->documentTypes($school);
            $this->administrator($school);
        });
    }

    protected function settings(School $school): void
    {
        $defaults = [
            'academic_year' => ['name' => '2026 / 2027', 'starts_on' => '2026-09-07', 'ends_on' => '2027-06-25'],
            'grading_scale' => [
                ['grade' => 'A', 'min' => 90, 'max' => 100, 'remark' => 'Excellent'],
                ['grade' => 'B', 'min' => 80, 'max' => 89, 'remark' => 'Very good'],
                ['grade' => 'C', 'min' => 70, 'max' => 79, 'remark' => 'Good'],
                ['grade' => 'D', 'min' => 60, 'max' => 69, 'remark' => 'Satisfactory'],
                ['grade' => 'F', 'min' => 0, 'max' => 59, 'remark' => 'Fail'],
            ],
            'admission_document_types' => [
                'Previous report card', 'Transcript', 'Transfer certificate',
                'Birth certificate', 'Passport photograph', 'Identification document',
            ],
        ];

        foreach ($defaults as $key => $value) {
            SchoolSetting::firstOrCreate(
                ['school_id' => $school->id, 'key' => $key],
                ['value' => $value],
            );
        }
    }

    /** The documents this school asks for, editable from the dashboard. */
    protected function documentTypes(School $school): void
    {
        $types = [
            ['Birth certificate', 'students', true, false],
            ['Passport photograph', 'students', true, false],
            ['Previous report card', 'students', false, false],
            ['Transfer certificate', 'students', false, false],
            ['Medical record', 'students', false, true],
            ['Degree certificate', 'teachers', true, false],
            ['Teaching licence', 'teachers', false, true],
            ['Employment contract', 'teachers', true, false],
            ['Identification document', 'general', true, true],
        ];

        foreach ($types as $position => [$name, $appliesTo, $required, $expires]) {
            \App\Models\DocumentType::firstOrCreate(
                ['school_id' => $school->id, 'name' => $name, 'applies_to' => $appliesTo],
                ['is_required' => $required, 'expires' => $expires, 'position' => $position],
            );
        }
    }

    protected function administrator(School $school): void
    {
        $administrator = User::firstOrCreate(
            ['email' => 'admin@gracefoundation.edu.lr'],
            [
                'school_id' => $school->id,
                'name' => 'Grace School Administrator',
                'password' => Hash::make('ChangeMe123!'),
                'status' => 'active',
            ],
        );

        $role = Role::where('school_id', $school->id)->where('slug', 'school-administrator')->firstOrFail();

        $administrator->roles()->syncWithoutDetaching([$role->id]);
    }
}
