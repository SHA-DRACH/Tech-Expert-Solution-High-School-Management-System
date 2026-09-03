<?php

namespace App\Http\Middleware;

use App\Support\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the signed-in area. By this point ResolveSchoolContext has run, so a
 * request either belongs to a school or is a super administrator working at
 * platform level. Anything else is an account that cannot safely be served.
 */
class EnsureSchoolContext
{
    public function __construct(private readonly SchoolContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if ($this->context->hasSchool() || $this->context->isUnrestricted()) {
            return $next($request);
        }

        abort(403, 'This account is not assigned to a school.');
    }
}
