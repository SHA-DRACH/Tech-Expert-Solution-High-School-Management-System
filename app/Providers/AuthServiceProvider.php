<?php

namespace App\Providers;

use App\Models\Admission;
use App\Models\Assessment;
use App\Models\AdmissionDocument;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Policies\AdmissionDocumentPolicy;
use App\Policies\AdmissionPolicy;
use App\Policies\AssessmentPolicy;
use App\Policies\GuardianPolicy;
use App\Policies\RolePolicy;
use App\Policies\SchoolPolicy;
use App\Policies\StudentPolicy;
use App\Policies\UserPolicy;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected array $policies = [
        School::class => SchoolPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Student::class => StudentPolicy::class,
        Guardian::class => GuardianPolicy::class,
        Admission::class => AdmissionPolicy::class,
        Assessment::class => AssessmentPolicy::class,
        AdmissionDocument::class => AdmissionDocumentPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // A suspended account can do nothing. Super administrators bypass
        // permission checks, but never the tenant scope: they still only reach
        // the school currently selected in SchoolContext.
        Gate::before(function (User $user) {
            if (! $user->isActive()) {
                return false;
            }

            return $user->isSuperAdministrator() ? true : null;
        });

        // One gate per catalogue entry, so views can write @can('students.view').
        foreach (Permissions::slugs() as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }
    }
}
