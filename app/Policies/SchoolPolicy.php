<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    /** Only platform administrators see the full list of schools. */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdministrator();
    }

    public function view(User $user, School $school): bool
    {
        return $user->isSuperAdministrator() || $user->school_id === $school->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdministrator();
    }

    /** School administrators may edit their own school's profile and branding. */
    public function update(User $user, School $school): bool
    {
        return $user->hasPermission('settings.manage') && $user->school_id === $school->id;
    }
}
