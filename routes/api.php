<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ParentApiController;
use App\Http\Middleware\ResolveSchoolContext;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Token-authenticated endpoints for the planned Flutter application
| (spec section 64). They reuse the same services and authorization rules as
| the web portals; nothing here is a second implementation of the rules.
|
| ResolveSchoolContext runs on this group too, so the tenant scope applies
| exactly as it does on the web.
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.login');

Route::middleware([
    'auth:sanctum',
    // Order matters: identify the user, resolve their school from them, then
    // bind route models so the binding is tenant-scoped.
    ResolveSchoolContext::class,
    SubstituteBindings::class,
    'school.context',
])->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::prefix('parent')->name('api.parent.')->group(function () {
        Route::get('/children', [ParentApiController::class, 'children'])->name('children');
        Route::get('/children/{child}', [ParentApiController::class, 'summary'])->name('summary');
        Route::get('/children/{child}/grades', [ParentApiController::class, 'grades'])->name('grades');
        Route::get('/children/{child}/attendance', [ParentApiController::class, 'attendance'])->name('attendance');
        Route::get('/children/{child}/fees', [ParentApiController::class, 'fees'])->name('fees');

        Route::get('/announcements', [ParentApiController::class, 'announcements'])->name('announcements');
        Route::get('/events', [ParentApiController::class, 'events'])->name('events');
        Route::get('/notifications', [ParentApiController::class, 'notifications'])->name('notifications');
    });
});
