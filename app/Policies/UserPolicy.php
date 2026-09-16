<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Delegation;
use App\Support\SchoolContext;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id
            || ($user->hasPermission('users.view') && $this->sameSchool($user, $target));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.update')
            && $this->sameSchool($user, $target)
            // Nobody edits an account that holds authority they do not.
            && Delegation::canManageAccount($user, $target);
    }

    /** Nobody may suspend their own account, or an account in another school. */
    public function suspend(User $user, User $target): bool
    {
        return $user->id !== $target->id
            && $user->hasPermission('users.suspend')
            && $this->sameSchool($user, $target)
            && ! $target->isSuperAdministrator()
            && Delegation::canManageAccount($user, $target);
    }

    protected function sameSchool(User $user, User $target): bool
    {
        if ($target->school_id === null) {
            return false;
        }

        $activeSchoolId = app(SchoolContext::class)->schoolId();

        if ($user->isSuperAdministrator()) {
            return $activeSchoolId === null || $target->school_id === $activeSchoolId;
        }

        return $target->school_id === $user->school_id && $target->school_id === $activeSchoolId;
    }
}
