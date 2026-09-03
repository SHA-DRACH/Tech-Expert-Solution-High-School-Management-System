<?php

namespace App\Http\Requests;

use App\Actions\GrantPortalAccess;
use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Guardian::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
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
        return GrantPortalAccess::attributes();
    }
}
