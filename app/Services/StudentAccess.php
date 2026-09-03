<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentPermission;
use Illuminate\Support\Collection;

/**
 * Resolves what a student account is allowed to do (spec section 24).
 *
 * Three layers, most specific first:
 *
 *   1. a row for this student            - the administrator's override
 *   2. a row with a null student_id      - the school-wide default
 *   3. the catalogue default             - the platform's starting position
 *
 * None of it is hard-coded per student, so an administrator changes access from
 * the dashboard alone.
 *
 * Deliberately stateless. An earlier version memoised each student's resolved
 * set, which went stale the moment anything wrote a permission outside this
 * class and silently kept granting access that had just been revoked. Access
 * control is the wrong place to trade correctness for one small query.
 */
class StudentAccess
{
    public function allows(Student $student, string $ability): bool
    {
        return $this->for($student)->get($ability, false);
    }

    /** @return Collection<string, bool> every ability with its effective value */
    public function for(Student $student): Collection
    {
        $rows = StudentPermission::query()
            ->where('school_id', $student->school_id)
            ->where(fn ($query) => $query->whereNull('student_id')->orWhere('student_id', $student->id))
            ->get();

        $schoolDefaults = $rows->whereNull('student_id')->keyBy('ability');
        $overrides = $rows->where('student_id', $student->id)->keyBy('ability');

        return collect(StudentPermission::ABILITIES)
            ->map(function (array $definition, string $ability) use ($schoolDefaults, $overrides) {
                if ($overrides->has($ability)) {
                    return (bool) $overrides[$ability]->allowed;
                }

                if ($schoolDefaults->has($ability)) {
                    return (bool) $schoolDefaults[$ability]->allowed;
                }

                return $definition['default'];
            });
    }
}
