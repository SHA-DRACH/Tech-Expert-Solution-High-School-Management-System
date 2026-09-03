<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolOwnership;

class AdmissionPolicy
{
    use ChecksSchoolOwnership;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('admissions.view');
    }

    public function view(User $user, Admission $admission): bool
    {
        return $this->allows($user, 'admissions.view', $admission);
    }

    public function review(User $user, Admission $admission): bool
    {
        return $this->allows($user, 'admissions.review', $admission);
    }

    public function approve(User $user, Admission $admission): bool
    {
        return $this->allows($user, 'admissions.approve', $admission);
    }

    public function reject(User $user, Admission $admission): bool
    {
        return $this->allows($user, 'admissions.reject', $admission);
    }

    public function enroll(User $user, Admission $admission): bool
    {
        return $this->allows($user, 'admissions.enroll', $admission)
            && $admission->status === 'approved';
    }
}
