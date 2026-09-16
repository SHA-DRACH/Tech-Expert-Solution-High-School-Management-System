<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Academics';

    protected $fillable = ['school_id', 'name', 'starts_on', 'ends_on', 'is_current'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class)->orderBy('number');
    }

    /** True once the year is organised as marking periods within semesters. */
    public function usesPeriods(): bool
    {
        return $this->terms()->whereNotNull('semester')->exists();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** The year the school is currently working in, falling back to the newest. */
    public static function active(): ?self
    {
        return static::query()->current()->first() ?? static::query()->latest('starts_on')->first();
    }
}
