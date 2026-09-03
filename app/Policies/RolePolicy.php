<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\SchoolContext;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view') && $this->belongsToActiveSchool($user, $role);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && $this->belongsToActiveSchool($user, $role);
    }

    /** System roles are part of the platform contract and are never deleted. */
    public function delete(User $user, Role $role): bool
    {
        return ! $role->isProtected()
            && $user->hasPermission('roles.manage')
            && $this->belongsToActiveSchool($user, $role)
            && $role->users()->doesntExist();
    }

    protected function belongsToActiveSchool(User $user, Role $role): bool
    {
        // Platform roles (school_id null) are managed by super administrators only.
        if ($role->isPlatformRole()) {
            return $user->isSuperAdministrator();
        }

        $activeSchoolId = app(SchoolContext::class)->schoolId();

        if ($user->isSuperAdministrator()) {
            return $activeSchoolId === null || $role->school_id === $activeSchoolId;
        }

        return $role->school_id === $user->school_id && $role->school_id === $activeSchoolId;
    }
}
