<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform-level administration: the list of schools on the platform and the
 * school selector that lets a super administrator work inside one of them.
 */
class PlatformSchoolController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', School::class);

        $schools = School::query()
            ->withCount(['users', 'students'])
            ->orderBy('name')
            ->paginate(20);

        return view('platform.schools', compact('schools'));
    }

    /**
     * Enter a school's workspace. Only a super administrator may do this, and
     * the choice is stored server-side in the session, never trusted from a
     * request field on later requests.
     */
    public function select(Request $request, School $school, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('viewAny', School::class);

        $request->session()->put('platform.active_school_id', $school->id);

        $audit->log('school_selected', 'Platform', "Entered the workspace for {$school->name}.", $school);

        return redirect()->route('dashboard')->with('status', "You are now working in {$school->name}.");
    }

    public function clearSelection(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', School::class);

        $request->session()->forget('platform.active_school_id');

        return redirect()->route('platform.schools')->with('status', 'Returned to platform level.');
    }
}
