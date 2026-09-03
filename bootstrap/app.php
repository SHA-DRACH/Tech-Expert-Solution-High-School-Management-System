<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         | The tenant must be resolved before route-model binding runs, so that
         | binding a {student} or {admission} is filtered by SchoolScope and an
         | id from another school simply does not resolve. SubstituteBindings is
         | therefore lifted out of its default position and re-added after
         | ResolveSchoolContext.
         */
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                /*
                 | Must sit between two things, which is why it is the first
                 | entry here rather than prepended to the group.
                 |
                 | After EncryptCookies, or it reads the still-encrypted cookie,
                 | fails to parse it, and throws away every "keep me signed in"
                 | cookie in the school.
                 |
                 | Before ResolveSchoolContext, which is the first middleware to
                 | ask who the user is - and asking is what triggers the guard's
                 | TypeError on a stale cookie.
                 */
                App\Http\Middleware\DiscardUnusableRememberCookie::class,

                App\Http\Middleware\ResolveSchoolContext::class,
                SubstituteBindings::class,
                App\Http\Middleware\EnsureAccountIsActive::class,
            ],
        );

        /*
         | SubstituteBindings is lifted out of the API group and re-added inside
         | the authenticated group in routes/api.php, after Sanctum has
         | identified the user and the tenant has been resolved from them.
         | Binding a {child} before that would not be tenant-scoped.
         */
        $middleware->api(remove: [SubstituteBindings::class]);

        $middleware->alias([
            'school.context' => App\Http\Middleware\EnsureSchoolContext::class,
            'permission' => App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
