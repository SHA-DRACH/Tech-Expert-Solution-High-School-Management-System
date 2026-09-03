<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\StudentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "My account" - the one settings page every signed-in person has.
 *
 * Deliberately one controller rather than one per role. An administrator, a
 * teacher and a parent all need the same three things: correct their own name,
 * change their own email, and change their own password. What differs is only
 * what is shown alongside it, and a Blade `@if` is a better answer to that than
 * three controllers that drift apart.
 *
 * Nothing here is gated on a permission slug. Every account may edit its own
 * profile - including a parent, who holds no permissions at all - and no account
 * may edit anyone else's from this screen. `users.update` is for administrators
 * managing *other* people's accounts, and that lives in UserManagementController.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $user->load(['roles.permissions:id,name,slug', 'guardianProfile.students', 'studentProfile', 'teacherProfile.department']);

        /*
         | A student's own record is governed by their school (section 24).
         | `edit_profile` defaults to off, so by default a student sees their
         | details and cannot change them - exactly the example in the spec.
         |
         | The password form is never gated. Being unable to change your own
         | password is an account-security problem, not a profile preference,
         | and no school setting should be able to create one.
         */
        $student = $user->studentProfile;
        $access = $student ? app(StudentAccess::class)->for($student) : null;

        return view('profile.edit', [
            'user' => $user,
            'canViewDetails' => $access === null || $access->get('view_profile', true),
            'canEditDetails' => $access === null || $access->get('edit_profile', false),
            /*
             | What this account can actually do, shown to the person holding it.
             | "What am I allowed to do here?" is a fair question, and answering
             | it plainly saves a support call - it is the same union of role
             | permissions the system itself enforces, not a description of it.
             */
            'permissions' => $user->roles->flatMap->permissions->unique('slug')->sortBy('name')->values(),
            'teacher' => $user->teacherProfile,
            'children' => $user->guardianProfile?->students ?? collect(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        // Re-checked here, not only in the view. Hiding a form is a courtesy;
        // a school that has switched this off has to be enforced on the backend.
        if ($student = $user->studentProfile) {
            abort_unless(
                app(StudentAccess::class)->allows($student, 'edit_profile'),
                403,
                'Your school has not enabled changing your own details.'
            );
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $original = $user->only(['name', 'email']);

        $user->update(['name' => $data['name'], 'email' => $data['email']]);

        // A teacher's phone number belongs on the staff record, which is what
        // the rest of the school reads; keeping a second copy on the account
        // would leave two numbers that disagree.
        if ($request->filled('phone') && $user->teacherProfile) {
            $user->teacherProfile->update(['phone' => $data['phone']]);
        }

        $audit->log('updated', 'Users', "{$user->name} updated their own profile.", $user, $original, $data);

        return back()->with('status', 'Your profile was updated.');
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'password.min' => 'The new password must be at least 12 characters.',
        ]);

        /*
         | The current password is required even though the person is already
         | signed in. It is what stops an unattended, unlocked screen from being
         | turned into a permanent account takeover, and it is the reason this
         | is not simply a field on the profile form above.
         */
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'That is not your current password.'])
                ->with('password_tab', true);
        }

        $user->update(['password' => $data['password']]);

        // Other sessions are invalidated: if the reason for changing a password
        // is that someone else has it, leaving their session alive defeats it.
        $request->session()->regenerate();

        $audit->log('updated', 'Users', "{$user->name} changed their own password.", $user);

        return back()->with('status', 'Your password was changed.');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $teacher = $user->teacherProfile;

        if ($teacher === null) {
            return back()->withErrors(['photo' => 'Only staff records carry a photograph.']);
        }

        $previous = $teacher->photo_path;

        $teacher->update(['photo_path' => $request->file('photo')->store('teachers', 'public')]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('status', 'Your photograph was updated.');
    }
}
