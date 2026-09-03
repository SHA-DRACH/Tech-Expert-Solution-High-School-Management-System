<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single entry point that forwards each account to the portal matching what it
 * actually is, so /portal works for everyone regardless of role.
 */
class PortalController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->guardianProfile()->exists()) {
            return redirect()->route('parent.dashboard');
        }

        if ($user->studentProfile()->exists()) {
            return redirect()->route('student.dashboard');
        }

        if (Teacher::where('user_id', $user->id)->exists()) {
            return redirect()->route('teaching.dashboard');
        }

        if ($user->hasPermission('dashboard.view')) {
            return redirect()->route('dashboard');
        }

        abort(403, 'This account is not linked to a portal yet. Please contact your school administrator.');
    }
}
