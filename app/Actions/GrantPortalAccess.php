<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The optional "give this person a login" half of a create-person form.
 *
 * Adding a teacher, a student or a guardian creates the *person*. Whether they
 * also get a way to sign in is a separate decision - a school enrols a hundred
 * students and hands out perhaps a dozen portal accounts - so it stays optional.
 *
 * But it used to be a separate *screen*, and that was the confusing part:
 * Workspace created people with no password anywhere in sight, while
 * Administration created logins, and nothing on either page said the two were
 * halves of the same job. This puts the same three fields on all three forms so
 * both can be done in one pass, and holds them to one shape so they cannot
 * drift apart again.
 */
class GrantPortalAccess
{
    public function __construct(private readonly CreateSchoolUser $createUser) {}

    /**
     * Validation rules for the block, merged into the form's own.
     *
     * Everything is `nullable` on its own and required only once a password has
     * been typed, so leaving the whole section blank is always valid.
     */
    public static function rules(): array
    {
        return [
            'account_password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'account_email' => ['nullable', 'required_with:account_password', 'email', 'max:255', Rule::unique('users', 'email')],
            'account_role_id' => ['nullable', 'required_with:account_password', 'integer'],
        ];
    }

    public static function messages(): array
    {
        return [
            'account_password.min' => 'The password must be at least 12 characters.',
            'account_password.confirmed' => 'The two passwords do not match.',
            'account_email.required_with' => 'An email address is needed for the login — it is what they sign in with.',
            'account_email.unique' => 'That email address already has an account.',
            'account_role_id.required_with' => 'Choose what this account is allowed to do.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'account_email' => 'login email address',
            'account_password' => 'password',
            'account_role_id' => 'role',
        ];
    }

    /** Was the section filled in at all? */
    public static function wanted(Request $request): bool
    {
        return filled($request->input('account_password'));
    }

    /**
     * Create the login and link it to the person.
     *
     * @param  array{teacher_id?: int, student_id?: int, guardian_id?: int}  $link
     */
    public function handle(Request $request, string $name, array $link): ?User
    {
        if (! self::wanted($request)) {
            return null;
        }

        // Re-resolved through the tenant-scoped query: a role id is never
        // trusted straight from the form (section 59).
        $role = Role::inCurrentSchool()->findOrFail($request->integer('account_role_id'));

        return $this->createUser->handle([
            'name' => $name,
            'email' => $request->input('account_email'),
            'password' => $request->input('account_password'),
            'role_id' => $role->id,
        ] + $link);
    }
}
