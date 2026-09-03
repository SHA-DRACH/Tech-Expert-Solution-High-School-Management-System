<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail, SoftDeletes;

    protected string $auditModule = 'Academics';

    protected $fillable = ['school_id', 'school_class_id', 'class_teacher_id', 'name', 'capacity', 'room'];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * A readable name for the section.
     *
     * Sections are conventionally named after their grade ("10A" under
     * "Grade 10"), so the level is stripped from the suffix to avoid rendering
     * "Grade 10 10A". The result reads "Grade 10A".
     */
    public function getFullNameAttribute(): string
    {
        $className = $this->schoolClass?->name;

        if (! $className) {
            return $this->name;
        }

        $suffix = ltrim($this->name, '0123456789 -');

        return $suffix === '' ? $className : $className.$suffix;
    }
}
