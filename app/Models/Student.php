<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use BelongsToSchool, HasDocuments, HasFactory, RecordsAuditTrail, SoftDeletes;

    /** Which document types apply to this kind of record. */
    protected string $documentSubject = 'students';

    public const STATUSES = ['active', 'suspended', 'transferred', 'graduated', 'withdrawn', 'archived'];

    protected string $auditModule = 'Students';

    protected $fillable = [
        'school_id', 'user_id', 'student_number', 'first_name', 'middle_name', 'last_name',
        'gender', 'date_of_birth', 'nationality', 'photo_path', 'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['relationship', 'is_primary', 'can_view_academics', 'can_view_finance'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The subjects this student takes, per academic year.
     *
     * Recorded rather than inferred from the class. A grade may offer a subject
     * that only some of its students take, and inferring meant all of them
     * appeared on its mark sheet.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subject')
            ->withPivot(['school_id', 'academic_year_id'])
            ->withTimestamps();
    }

    /** Subjects taken in a given year, defaulting to the current one. */
    public function subjectsFor(?AcademicYear $year = null): BelongsToMany
    {
        $year ??= AcademicYear::active();

        return $this->subjects()->wherePivot('academic_year_id', $year?->id);
    }

    /** The placement for the academic year the school is currently working in. */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
            ->whereHas('academicYear', fn (Builder $query) => $query->where('is_current', true))
            ->latestOfMany();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function assessmentScores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(StudentPermission::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' ');
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /** Outstanding balance across every invoice, in minor units. */
    public function outstandingMinor(): int
    {
        return (int) $this->invoices()
            ->outstanding()
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->balanceMinor());
    }

    /**
     * Share of school days attended. Null when nothing has been recorded yet,
     * so the interface can say "not recorded" rather than showing 0%.
     */
    public function attendanceRate(): ?float
    {
        $total = $this->attendanceRecords()->count();

        if ($total === 0) {
            return null;
        }

        return round($this->attendanceRecords()->present()->count() / $total * 100, 1);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return Search::apply($query, $term, [
            'words' => ['first_name', 'middle_name', 'last_name'],
            'identifiers' => ['student_number'],
            // Searching by parent name, as the spec asks for.
            'relations' => ['guardians' => ['first_name', 'last_name']],
        ]);
    }

    public function scopeInSection(Builder $query, int $sectionId): Builder
    {
        return $query->whereHas('enrollments', fn (Builder $q) => $q->where('section_id', $sectionId));
    }
}
