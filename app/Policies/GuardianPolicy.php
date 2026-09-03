<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolOwnership;

class GuardianPolicy
{
    use ChecksSchoolOwnership;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('guardians.view');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->allows($user, 'guardians.view', $guardian)
            || ($guardian->user_id !== null && $guardian->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('guardians.create');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->allows($user, 'guardians.update', $guardian);
    }

    public function archive(User $user, Guardian $guardian): bool
    {
        return $this->allows($user, 'guardians.archive', $guardian);
    }
}
