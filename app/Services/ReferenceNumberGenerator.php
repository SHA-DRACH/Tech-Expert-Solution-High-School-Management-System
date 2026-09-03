<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues the human-readable references used across the platform:
 *
 *   student number      GFI-2026-00125
 *   application number  ADM-2026-00458
 *
 * Each school keeps its own counter per series and year, claimed under a row
 * lock so two simultaneous submissions cannot be handed the same number.
 */
class ReferenceNumberGenerator
{
    public function studentNumber(School $school): string
    {
        return sprintf(
            '%s-%s-%05d',
            $school->numberPrefix(),
            $year = (string) now()->year,
            $this->claim($school, 'student', $year),
        );
    }

    public function applicationNumber(School $school): string
    {
        return sprintf(
            'ADM-%s-%05d',
            $year = (string) now()->year,
            $this->claim($school, 'admission', $year),
        );
    }

    /** Reserve the next value in a series, locking the counter row for the duration. */
    protected function claim(School $school, string $key, string $period): int
    {
        $attributes = ['school_id' => $school->id, 'sequence_key' => $key, 'period' => $period];

        // Create the counter first so the lock below always has a row to take.
        // A concurrent creator loses the unique constraint race harmlessly.
        try {
            NumberSequence::withoutGlobalScope(SchoolScope::class)
                ->firstOrCreate($attributes, ['next_value' => 1]);
        } catch (UniqueConstraintViolationException) {
            // Another request created it first, which is exactly what we wanted.
        }

        return DB::transaction(function () use ($attributes) {
            $sequence = NumberSequence::withoutGlobalScope(SchoolScope::class)
                ->where($attributes)
                ->lockForUpdate()
                ->firstOrFail();

            $value = $sequence->next_value;

            $sequence->next_value = $value + 1;
            $sequence->save();

            return $value;
        });
    }
}
