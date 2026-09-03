<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const TYPES = ['assignment', 'test', 'exam', 'other'];

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    protected string $auditModule = 'Examinations & grades';

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'examination_id', 'section_id',
        'subject_id', 'teacher_id', 'title', 'instructions', 'attachment_path', 'attachment_name',
        'type', 'max_score', 'weight', 'starts_at', 'ends_at',
        'status', 'submitted_at', 'approved_by', 'approved_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'weight' => 'decimal:2',
        ];
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    /** Only approved work is visible to students and parents. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }
}
