<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')->id),
            ],
            // Blank means "leave the password alone"; a password is only
            // written when someone deliberately types a new one.
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /*
                 | Section 59: never trust ids from the frontend. Every role id
                 | is re-resolved against the current school, so a posted id
                 | belonging to another tenant simply does not resolve and the
                 | request is rejected rather than silently granting a role from
                 | somewhere else.
                 */
                $submitted = collect($this->input('roles', []))->map(fn ($id) => (int) $id)->unique();

                $valid = Role::inCurrentSchool()->whereIn('id', $submitted)->pluck('id');

                if ($valid->count() !== $submitted->count()) {
                    $validator->errors()->add('roles', 'Select roles that belong to this school.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'The password must be at least 12 characters.',
            'roles.required' => 'An account needs at least one role, or its holder can sign in and do nothing.',
        ];
    }
}
