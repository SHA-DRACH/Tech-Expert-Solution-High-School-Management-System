<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Academics';

    /**
     * A term row is a marking period when it carries a semester number.
     *
     * Six to a year, three to a semester. The same table serves a school still
     * keeping plain terms, which simply leaves `semester` empty.
     */
    public const PERIODS_PER_YEAR = 6;

    protected $fillable = ['school_id', 'academic_year_id', 'name', 'sequence', 'semester', 'starts_on', 'ends_on', 'is_current'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isPeriod(): bool
    {
        return $this->semester !== null;
    }

    /** "1st period" … "6th period" - the name a Liberian grade sheet uses. */
    public static function periodName(int $number): string
    {
        $suffix = match ($number) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };

        return $number.$suffix.' period';
    }

    /** Where this period falls in its year, 1-6, counted from the calendar. */
    public function periodNumber(): ?int
    {
        if (! $this->isPeriod()) {
            return null;
        }

        return static::where('academic_year_id', $this->academic_year_id)
            ->whereNotNull('semester')
            ->where('sequence', '<=', $this->sequence)
            ->count();
    }

    /** "3rd period", or the stored name for a plain term. */
    public function label(): string
    {
        $number = $this->periodNumber();

        return $number ? static::periodName($number) : $this->name;
    }

    /** Which semester the nth period of the year falls in. */
    public static function semesterForPeriod(int $number): int
    {
        return (int) ceil($number / Semester::PERIODS_PER_SEMESTER);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public static function active(): ?self
    {
        return static::query()->current()->first() ?? static::query()->orderByDesc('sequence')->first();
    }
}
