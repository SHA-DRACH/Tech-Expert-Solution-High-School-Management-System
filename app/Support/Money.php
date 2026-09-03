<?php

namespace App\Support;

/**
 * Money is stored in minor units (cents) as integers so totals never drift
 * through floating-point arithmetic. This turns those integers into something
 * a person can read.
 *
 * The currency symbol belongs to the school, not to the platform. Liberian
 * schools bill in Liberian dollars or United States dollars and some quote
 * both, so a hard-coded symbol would have been wrong for the pilot school's
 * own neighbours, never mind a future tenant.
 */
class Money
{
    /** Used when no school is in context: console commands, tests, seeders. */
    public const CURRENCY = 'L$';

    /**
     * The symbol for the school currently in context.
     *
     * The lookup is memoised on the School model instance rather than here,
     * because a model instance lives exactly one request. Caching it on this
     * class - or on an injected service - would outlive the request that built
     * it and an administrator could change the currency and watch nothing
     * happen, which is a bug this project has already paid for three times.
     */
    public static function currency(): string
    {
        $school = app(SchoolContext::class)->school();

        return $school?->currencySymbol() ?? self::CURRENCY;
    }

    public static function format(int|float|null $minor, bool $withSymbol = true): string
    {
        $amount = number_format(((int) $minor) / 100, 2);

        return $withSymbol ? self::currency().' '.$amount : $amount;
    }

    /** A compact form for dashboard tiles: L$ 1.2M, L$ 340k. */
    public static function compact(int|float|null $minor): string
    {
        $major = ((int) $minor) / 100;
        $symbol = self::currency();

        return match (true) {
            abs($major) >= 1_000_000 => $symbol.' '.round($major / 1_000_000, 1).'M',
            abs($major) >= 1_000 => $symbol.' '.round($major / 1_000, 1).'k',
            default => $symbol.' '.number_format($major, 0),
        };
    }

    public static function toMinor(int|float|string $major): int
    {
        return (int) round(((float) $major) * 100);
    }
}
