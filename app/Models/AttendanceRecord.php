<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToSchool, HasFactory;

    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = [
        'school_id', 'student_id', 'section_id', 'academic_year_id', 'term_id',
        'recorded_on', 'status', 'remark', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['recorded_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Counts as attending for the purposes of an attendance percentage. */
    public function scopePresent(Builder $query): Builder
    {
        return $query->whereIn('status', ['present', 'late']);
    }
}
