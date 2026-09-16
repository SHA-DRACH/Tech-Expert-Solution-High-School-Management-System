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

    /**
     * Kinds of work a teacher may set within a marking period.
     *
     * The first four are the parts a Liberian period grade is built from; the
     * rest predate periods and stay valid for anything else a school records.
     */
    public const TYPES = ['period_test', 'quiz', 'assignment', 'attendance', 'test', 'exam', 'other'];

    /** The parts of a period grade, in the order a grade sheet shows them. */
    public const PERIOD_COMPONENTS = [
        'period_test' => 'Period test',
        'quiz' => 'Quiz',
        'assignment' => 'Assignment',
        'attendance' => 'Attendance',
    ];

    /**
     * An end-of-semester examination.
     *
     * Deliberately not in TYPES: it belongs to a semester rather than a period,
     * and is only ever created by the grade sheet, while the administration
     * has examination entry open.
     */
    public const SEMESTER_EXAM = 'semester_exam';

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    protected string $auditModule = 'Examinations & grades';

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'semester_id', 'examination_id', 'section_id',
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

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
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
