<?php

namespace App\Actions;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates a staff or portal account and links it to the person it represents.
 *
 * Kept out of the controller so the same flow can be reused by the enrollment
 * pipeline and, later, an API endpoint.
 */
class CreateSchoolUser
{
    public function __construct(private readonly SchoolContext $context) {}

    /** @param  array<string, mixed>  $data */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Every lookup goes through the tenant-scoped query, so an id from
            // another school resolves to nothing and the account is never made.
            $role = Role::inCurrentSchool()->findOrFail($data['role_id']);

            $user = User::create([
                'school_id' => $this->context->schoolId(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'active',
            ]);

            $user->roles()->sync([$role->id]);

            if (! empty($data['guardian_id'])) {
                Guardian::whereNull('user_id')
                    ->findOrFail($data['guardian_id'])
                    ->update(['user_id' => $user->id]);
            }

            if (! empty($data['student_id'])) {
                Student::whereNull('user_id')
                    ->findOrFail($data['student_id'])
                    ->update(['user_id' => $user->id]);
            }

            /*
             | Teachers were missing here, which meant a staff record could
             | never be given a login: adding a teacher created the person, and
             | nothing in the application could attach an account to them. The
             | teacher then signed in to nothing, or more often could not sign
             | in at all.
             */
            if (! empty($data['teacher_id'])) {
                Teacher::whereNull('user_id')
                    ->findOrFail($data['teacher_id'])
                    ->update(['user_id' => $user->id]);
            }

            return $user;
        });
    }
}
