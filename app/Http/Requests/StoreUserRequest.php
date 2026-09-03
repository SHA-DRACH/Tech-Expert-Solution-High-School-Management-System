<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            // Each of these is re-checked below against the current school
            // rather than trusted as an id from the form.
            'role_id' => ['required', 'integer'],
            'guardian_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $role = Role::inCurrentSchool()->find($this->integer('role_id'));

                if ($role === null) {
                    $validator->errors()->add('role_id', 'Select a role that belongs to this school.');

                    return;
                }

                if ($this->filled('guardian_id') && Guardian::whereNull('user_id')->find($this->integer('guardian_id')) === null) {
                    $validator->errors()->add('guardian_id', 'Select a parent or guardian from this school who has no account yet.');
                }

                if ($this->filled('student_id') && Student::whereNull('user_id')->find($this->integer('student_id')) === null) {
                    $validator->errors()->add('student_id', 'Select a student from this school who has no account yet.');
                }

                if ($this->filled('teacher_id') && Teacher::whereNull('user_id')->find($this->integer('teacher_id')) === null) {
                    $validator->errors()->add('teacher_id', 'Select a member of staff from this school who has no account yet.');
                }

                // Portal accounts are meaningless unless they point at a person.
                if ($role->slug === 'parent-guardian' && ! $this->filled('guardian_id')) {
                    $validator->errors()->add('guardian_id', 'Select the parent or guardian this account belongs to.');
                }

                if ($role->slug === 'student' && ! $this->filled('student_id')) {
                    $validator->errors()->add('student_id', 'Select the student this account belongs to.');
                }

                /*
                 | A teacher account that points at no staff record cannot enter
                 | a single mark: AssessmentPolicy resolves the teacher through
                 | users.id, finds nothing, and refuses. The account would look
                 | correct in the user list and be useless in practice, so the
                 | link is required rather than optional.
                 */
                if ($role->slug === 'teacher' && ! $this->filled('teacher_id')) {
                    $validator->errors()->add('teacher_id', 'Select the staff record this account belongs to, or the teacher will not be able to record any marks.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'The password must be at least 12 characters.',
        ];
    }
}
