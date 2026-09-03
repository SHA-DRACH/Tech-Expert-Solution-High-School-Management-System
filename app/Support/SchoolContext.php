<?php

namespace App\Support;

use App\Models\School;

/**
 * Holds the tenant (school) the current request or process is acting for.
 *
 * Every tenant-owned query is filtered against this object by SchoolScope, so
 * this is the single place that decides which school's data is reachable. It is
 * never settable from user input: the middleware resolves it from the
 * authenticated user or the public host, never from a request field.
 *
 * Exactly one of four modes is active at any time:
 *
 *   UNRESOLVED  no request in flight - console commands, seeders, queue jobs
 *               and test fixtures run without filtering
 *   SCHOOL      bound to one school; only that school's rows are reachable
 *   PLATFORM    a super administrator working above any single school
 *   DENIED      a request with no valid tenant; no tenant row is reachable
 */
class SchoolContext
{
    public const UNRESOLVED = 'unresolved';

    public const SCHOOL = 'school';

    public const PLATFORM = 'platform';

    public const DENIED = 'denied';

    protected string $mode = self::UNRESOLVED;

    protected ?School $school = null;

    /** Bind the context to a single school. */
    public function setSchool(School $school): static
    {
        $this->school = $school;
        $this->mode = self::SCHOOL;

        return $this;
    }

    /** Allow platform-wide access across every school (super administrators only). */
    public function allowAllSchools(): static
    {
        $this->school = null;
        $this->mode = self::PLATFORM;

        return $this;
    }

    /**
     * Reachable by no tenant record at all.
     *
     * Used when a request cannot be attributed to a school, so an unattributed
     * request returns nothing rather than falling back to unfiltered access.
     */
    public function denyAll(): static
    {
        $this->school = null;
        $this->mode = self::DENIED;

        return $this;
    }

    /** Return to the unfiltered console/fixture state. */
    public function forget(): static
    {
        $this->school = null;
        $this->mode = self::UNRESOLVED;

        return $this;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function schoolId(): ?int
    {
        return $this->school?->id;
    }

    public function hasSchool(): bool
    {
        return $this->mode === self::SCHOOL && $this->school !== null;
    }

    public function isUnrestricted(): bool
    {
        return $this->mode === self::PLATFORM || $this->mode === self::UNRESOLVED;
    }

    public function isDenied(): bool
    {
        return $this->mode === self::DENIED;
    }

    /** True when a super administrator is working above any single school. */
    public function isPlatform(): bool
    {
        return $this->mode === self::PLATFORM;
    }

    /**
     * Run a callback against a specific school, restoring the previous context
     * afterwards. Used by seeders, jobs and console commands that act on one
     * school at a time.
     */
    public function for(School $school, callable $callback): mixed
    {
        $previous = [$this->school, $this->mode];

        $this->setSchool($school);

        try {
            return $callback($school);
        } finally {
            [$this->school, $this->mode] = $previous;
        }
    }
}
