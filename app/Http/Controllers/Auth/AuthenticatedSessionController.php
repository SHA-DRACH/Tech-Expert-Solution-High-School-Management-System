<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The credentials provided do not match our records.',
            ]);
        }

        $user = $request->user();

        // A suspended account must not be given a session at all.
        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Please contact your school administrator.',
            ]);
        }

        $request->session()->regenerate();

        $audit->log('signed_in', 'Authentication', "{$user->name} signed in.", $user);

        return redirect()->intended($this->destinationFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Everyone is sent through the portal router, which forwards each account
     * to the workspace matching what it actually is.
     *
     * Routing on `dashboard.view` alone was wrong: teachers hold that
     * permission, so they landed on the administrator dashboard instead of
     * their own portal. Identity decides first, permission second.
     */
    protected function destinationFor($user): string
    {
        return route('portal');
    }
}
