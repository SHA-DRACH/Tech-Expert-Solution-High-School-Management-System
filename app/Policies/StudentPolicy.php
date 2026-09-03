<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolOwnership;

class StudentPolicy
{
    use ChecksSchoolOwnership;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if ($this->allows($user, 'students.view', $student)) {
            return true;
        }

        // A student may always read their own record; a guardian may read the
        // record of a child they are linked to.
        return $this->isOwnRecord($user, $student) || $this->isLinkedGuardian($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->allows($user, 'students.update', $student);
    }

    public function archive(User $user, Student $student): bool
    {
        return $this->allows($user, 'students.archive', $student);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('students.export');
    }

    protected function isOwnRecord(User $user, Student $student): bool
    {
        return $student->user_id !== null && $student->user_id === $user->id;
    }

    protected function isLinkedGuardian(User $user, Student $student): bool
    {
        $guardian = $user->guardianProfile;

        if ($guardian === null || $guardian->school_id !== $student->school_id) {
            return false;
        }

        return $student->guardians()
            ->where('guardians.id', $guardian->id)
            ->exists();
    }
}
