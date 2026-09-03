<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCard extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Report cards';

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'term_id', 'school_class_id', 'section_id',
        'average', 'position', 'class_size', 'days_present', 'days_total',
        'teacher_comment', 'principal_comment', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['average' => 'decimal:2', 'published_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportCardItem::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function attendancePercentage(): ?float
    {
        if (! $this->days_total) {
            return null;
        }

        return round($this->days_present / $this->days_total * 100, 1);
    }
}
