<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Second line of defence behind SchoolScope.
 *
 * SchoolScope already keeps other schools' rows out of queries, but a record
 * can still reach a policy through an explicitly unscoped query or a route
 * binding resolved before the context was set. Every policy that guards a
 * tenant-owned record confirms ownership here as well.
 */
trait ChecksSchoolOwnership
{
    protected function ownsRecord(User $user, Model $record): bool
    {
        $recordSchoolId = $record->getAttribute('school_id');

        if ($recordSchoolId === null) {
            return false;
        }

        $activeSchoolId = app(SchoolContext::class)->schoolId();

        // A super administrator working inside a selected school is held to
        // that school; one working at platform level is not restricted here.
        if ($user->isSuperAdministrator()) {
            return $activeSchoolId === null || $recordSchoolId === $activeSchoolId;
        }

        return $recordSchoolId === $user->school_id && $recordSchoolId === $activeSchoolId;
    }

    /** Permission plus ownership: both must hold. */
    protected function allows(User $user, string $permission, Model $record): bool
    {
        return $user->hasPermission($permission) && $this->ownsRecord($user, $record);
    }
}
