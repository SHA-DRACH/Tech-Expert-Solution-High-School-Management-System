<?php

namespace App\Actions;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Organise an academic year as six marking periods in two semesters.
 *
 * Used when a year is created and when an existing year is converted, so the
 * two can never lay a year out differently.
 *
 * A year still keeping terms is converted in place: its terms become the first
 * periods, in order, so every mark and report card already filed against them
 * stays where it is. The missing periods are added. Dates are spread evenly
 * across the year as a starting point - the administrator sets the real ones,
 * because only the school knows whether a period is a month or a month and a
 * half.
 */
class SetUpPeriods
{
    /**
     * @return string|null a reason it could not be done, or null on success
     */
    public function handle(AcademicYear $year): ?string
    {
        $terms = Term::where('academic_year_id', $year->id)->orderBy('sequence')->orderBy('starts_on')->get();

        // Running it again would overwrite dates the school has already set.
        if ($terms->whereNotNull('semester')->count() === Term::PERIODS_PER_YEAR) {
            return null;
        }

        if ($terms->count() > Term::PERIODS_PER_YEAR) {
            return "{$year->name} has {$terms->count()} terms, more than the six periods a year can hold. Remove the extra empty terms first.";
        }

        $dates = $this->evenSplit($year->starts_on, $year->ends_on, Term::PERIODS_PER_YEAR);

        DB::transaction(function () use ($year, $terms, $dates) {
            // Out of the way first, so renaming never trips the unique name rule.
            foreach ($terms as $term) {
                $term->update(['name' => 'tmp-'.$term->id]);
            }

            for ($number = 1; $number <= Term::PERIODS_PER_YEAR; $number++) {
                $term = $terms->get($number - 1) ?? new Term([
                    'school_id' => $year->school_id,
                    'academic_year_id' => $year->id,
                    'is_current' => false,
                ]);

                $term->fill([
                    'name' => ucfirst(Term::periodName($number)),
                    'sequence' => $number,
                    'semester' => Term::semesterForPeriod($number),
                    'starts_on' => $dates[$number - 1][0],
                    'ends_on' => $dates[$number - 1][1],
                ])->save();
            }

            foreach ([1, 2] as $number) {
                $last = $dates[$number * 3 - 1][1];

                $this->semester($year, $number, $last->copy()->subDays(6), $last);
            }

            /*
             | The dates were just redrawn, so whichever term was current before
             | no longer means anything - a converted "Third Term" would
             | otherwise become the current 3rd period in September. The period
             | that contains today is the current one; failing that, the first.
             */
            if ($year->is_current) {
                // Cleared before the lookup: a model read while it was still
                // current would see nothing to change and never save.
                Term::where('is_current', true)->update(['is_current' => false]);

                $today = now()->toDateString();
                $periods = Term::where('academic_year_id', $year->id)->orderBy('sequence');

                $current = (clone $periods)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->first()
                    ?? (clone $periods)->first();

                $current?->update(['is_current' => true]);
            }
        });

        return null;
    }

    /** The semester a period belongs to, created if the year lacks it. */
    public function semester(AcademicYear $year, int $number, ?Carbon $examStarts = null, ?Carbon $examEnds = null): Semester
    {
        return Semester::firstOrCreate(
            ['academic_year_id' => $year->id, 'number' => $number],
            [
                'school_id' => $year->school_id,
                'name' => Semester::nameFor($number),
                'exam_starts_on' => $examStarts,
                'exam_ends_on' => $examEnds,
            ],
        );
    }

    /**
     * Split a date range into equal consecutive parts.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    protected function evenSplit(Carbon $start, Carbon $end, int $parts): array
    {
        $days = max($parts, $start->diffInDays($end) + 1);
        $size = intdiv((int) $days, $parts);
        $ranges = [];

        for ($i = 0; $i < $parts; $i++) {
            $from = $start->copy()->addDays($i * $size);
            $to = $i === $parts - 1 ? $end->copy() : $start->copy()->addDays(($i + 1) * $size - 1);
            $ranges[] = [$from, $to];
        }

        return $ranges;
    }
}
