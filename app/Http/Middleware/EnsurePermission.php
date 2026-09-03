<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate: middleware('permission:students.view').
 * Several permissions may be listed; any one of them admits the request.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($user->hasAnyPermission($permissions), 403, 'You do not have permission to access this area.');

        return $next($request);
    }
}
