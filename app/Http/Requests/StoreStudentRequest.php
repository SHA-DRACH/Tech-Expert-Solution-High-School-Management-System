<?php

namespace App\Http\Requests;

use App\Actions\GrantPortalAccess;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Student::STATUSES)],
            // The guardian is re-resolved through the tenant-scoped query in the
            // controller, so an id from another school cannot be attached.
            'guardian_id' => ['nullable', 'integer'],
            'relationship' => ['nullable', 'string', 'max:60'],
            // Optional: a school may add the student before deciding the class.
            'section_id' => ['nullable', 'integer'],
            // The optional login half of the form, kept in one place so the
            // teacher, student and guardian forms cannot drift apart.
        ] + GrantPortalAccess::rules();
    }

    public function messages(): array
    {
        return GrantPortalAccess::messages();
    }

    public function attributes(): array
    {
        return [
            'guardian_id' => 'parent or guardian',
            'date_of_birth' => 'date of birth',
        ] + GrantPortalAccess::attributes();
    }
}
