<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throws away a "keep me signed in" cookie that can no longer identify anyone.
 *
 * `SessionGuard::userFromRecaller()` ends with
 *
 *     hash_equals($this->hashPasswordForCookie($userPassword), $recallerHash)
 *         || hash_equals($userPassword, $recallerHash)
 *
 * and `$userPassword` is null whenever `retrieveByToken()` matched nobody. The
 * second call then gets null and raises a TypeError, so the request dies with a
 * 500 before it reaches any controller.
 *
 * That is not a rare state. `remember_token` is null on a freshly seeded user,
 * so re-seeding, restoring a backup, or deleting and recreating an account all
 * leave a browser holding a cookie that no longer resolves. The failure is
 * total: every request 500s, **including `/logout`**, so the person cannot even
 * sign out to clear the cookie that is breaking them. They have to know to
 * delete site data by hand, which no parent or teacher is going to work out.
 *
 * So the cookie is checked before the guard ever looks at it, and dropped if it
 * cannot identify a user. A stale remember-me should silently mean "you are
 * signed out", which is what the person expected anyway.
 */
class DiscardUnusableRememberCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard) {
            return $next($request);
        }

        $name = $guard->getRecallerName();
        $value = $request->cookies->get($name);

        if ($value === null) {
            return $next($request);
        }

        if ($this->identifiesSomeone($guard, $value)) {
            return $next($request);
        }

        /*
         | Removed from this request as well as from the browser. Queueing the
         | forget alone would still leave the guard reading the bad cookie off
         | the current request and throwing before the response is ever sent.
         */
        $request->cookies->remove($name);

        Cookie::queue(Cookie::forget($name));

        return $next($request);
    }

    protected function identifiesSomeone(SessionGuard $guard, string $value): bool
    {
        $recaller = new Recaller($value);

        if (! $recaller->valid()) {
            return false;
        }

        // The same lookup the guard is about to do. If this finds nobody, the
        // guard's $userPassword is null and the TypeError follows.
        return $guard->getProvider()->retrieveByToken($recaller->id(), $recaller->token()) !== null;
    }
}
