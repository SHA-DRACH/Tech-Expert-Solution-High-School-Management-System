<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for the mobile application (spec section 64).
 *
 * Sanctum personal access tokens rather than a session, so the same accounts
 * that sign in on the web can sign in from a phone.
 */
class AuthController extends Controller
{
    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // One message for both cases: telling an attacker which half was wrong
        // is how account enumeration starts.
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials provided do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active. Please contact your school administrator.'],
            ]);
        }

        // A fresh token per device, so signing out on a phone cannot take the
        // parent's other devices with it.
        $token = $user->createToken($data['device_name'], ['portal'])->plainTextToken;

        $audit->log('signed_in_api', 'Authentication',
            "{$user->name} signed in from {$data['device_name']}.", $user);

        return response()->json([
            'token' => $token,
            'user' => $this->profile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Only the token that made this call, not every device.
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /** @return array<string, mixed> */
    protected function profile(User $user): array
    {
        $guardian = $user->guardianProfile;
        $student = $user->studentProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'school' => $user->school?->only(['id', 'name', 'short_name', 'primary_color', 'secondary_color']),
            'roles' => $user->roles->pluck('slug'),
            'portal' => match (true) {
                $guardian !== null => 'parent',
                $student !== null => 'student',
                default => 'staff',
            },
        ];
    }
}
