<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * You can only hand out authority you already hold.
 *
 * A school delegates: an admissions head, an HR officer, a deputy who runs the
 * accounts. Before this rule, anyone trusted with `users.update` could give any
 * account - their own included - the School Administrator role, and anyone
 * with `roles.manage` could add any permission to a role they held. Delegating
 * a small job therefore delegated the whole school.
 *
 * The rule: a grant is refused when it would give someone a **privileged**
 * permission the person granting it does not hold. Privileged means control
 * over accounts, roles, settings, money, the audit trail, or signing off
 * results - see Permissions::PRIVILEGED. Ordinary working permissions are not
 * in the list on purpose: an HR officer onboarding a teacher has to be able to
 * give them the Teacher role without first being able to enter grades.
 *
 * The same test protects accounts that out-rank their editor, so an HR officer
 * cannot reset the principal's password or strip their roles.
 * Platform super administrators hold everything and pass every check.
 */
class Delegation
{
    /** Permissions in the given set that this person does not hold. */
    public static function beyond(User $actor, iterable $slugs): Collection
    {
        if ($actor->isSuperAdministrator()) {
            return collect();
        }

        return collect($slugs)->unique()
            ->intersect(Permissions::PRIVILEGED)
            ->diff($actor->permissionSlugs())
            ->values();
    }

    public static function canGrantRole(User $actor, Role $role): bool
    {
        return static::beyond($actor, $role->permissions()->pluck('slug'))->isEmpty();
    }

    /** May this person change an account's roles, password or details? */
    public static function canManageAccount(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        if ($target->isSuperAdministrator() && ! $actor->isSuperAdministrator()) {
            return false;
        }

        return static::beyond($actor, $target->permissionSlugs())->isEmpty();
    }
}
