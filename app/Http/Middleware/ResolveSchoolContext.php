<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Support\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for the request. Runs on every web request, before any
 * tenant-owned model is touched.
 *
 * Signed-in users are pinned to their own school and cannot change it. Super
 * administrators may work inside a school they select; that selection lives in
 * the session and is re-validated here on every request.
 */
class ResolveSchoolContext
{
    public function __construct(private readonly SchoolContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuperAdministrator()) {
            $this->resolveForSuperAdministrator($request);
        } elseif ($user?->school_id) {
            $school = School::find($user->school_id);

            abort_if($school === null || ! $school->is_active, 403, 'This school is not currently active.');

            $this->context->setSchool($school);
        } else {
            $this->resolveForGuest($request);
        }

        return $next($request);
    }

    private function resolveForSuperAdministrator(Request $request): void
    {
        $selectedId = $request->session()->get('platform.active_school_id');

        if ($selectedId && $school = School::find($selectedId)) {
            $this->context->setSchool($school);

            return;
        }

        $request->session()->forget('platform.active_school_id');
        $this->context->allowAllSchools();
    }

    /**
     * Guests browsing the public website are scoped to the school that owns the
     * host they arrived on, falling back to the first active school.
     */
    private function resolveForGuest(Request $request): void
    {
        $school = School::where('is_active', true)
            ->where('domain', $request->getHost())
            ->first()
            ?? School::where('is_active', true)->orderBy('id')->first();

        if ($school) {
            $this->context->setSchool($school);

            return;
        }

        $this->context->denyAll();
    }
}
