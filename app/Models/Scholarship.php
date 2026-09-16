<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A standing award against a student's fees.
 *
 * The model owns the arithmetic rather than leaving it to whichever screen
 * happens to be showing a figure. A discount that is worked out in three places
 * eventually disagrees with itself, and the place it shows up is a family being
 * asked for money they were told they did not owe.
 */
class Scholarship extends Model
{
    use BelongsToSchool, RecordsAuditTrail;

    /** Percentage of the bill, or a fixed sum off it. */
    public const TYPES = ['percentage', 'amount'];

    public const STATUSES = ['active', 'suspended', 'ended'];

    protected string $auditModule = 'Finance';

    protected $fillable = [
        'school_id', 'student_id', 'name', 'sponsor', 'reference',
        'type', 'percentage', 'amount_minor',
        'academic_year_id', 'term_id', 'status', 'starts_on', 'ends_on',
        'notes', 'awarded_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function auditLabel(): string
    {
        return '"'.$this->name.'" for '.($this->student?->full_name ?? 'a student');
    }

    /* ------------------------------------------------------------------ */
    /* What the award is worth                                             */
    /* ------------------------------------------------------------------ */

    /**
     * The discount this award takes off a bill of the given size.
     *
     * Never more than the bill itself: a fixed award larger than the fee would
     * otherwise produce a negative balance, which reads on screen as the school
     * owing the family money.
     */
    public function discountOn(int $totalMinor): int
    {
        if ($totalMinor <= 0) {
            return 0;
        }

        $discount = $this->type === 'percentage'
            ? (int) round($totalMinor * ((float) $this->percentage) / 100)
            : (int) $this->amount_minor;

        return max(0, min($discount, $totalMinor));
    }

    /** "50%" or "L$5,000.00" - how the award reads on a screen. */
    public function awardLabel(): string
    {
        return $this->type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->percentage, 2, '.', ''), '0'), '.').'%'
            : Money::format((int) $this->amount_minor);
    }

    /**
     * Whether this award should be applied to a bill for the given period.
     *
     * Suspended is deliberately distinct from ended: a school that pauses an
     * award pending a sponsor's payment still wants the record, and wants it
     * back without retyping every detail.
     */
    public function appliesTo(?int $academicYearId, ?int $termId, ?string $on = null): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // A null year on the award means "any year"; the same for the term.
        if ($this->academic_year_id !== null && $this->academic_year_id !== $academicYearId) {
            return false;
        }

        if ($this->term_id !== null && $this->term_id !== $termId) {
            return false;
        }

        $on ??= now()->toDateString();

        if ($this->starts_on !== null && $on < $this->starts_on->toDateString()) {
            return false;
        }

        if ($this->ends_on !== null && $on > $this->ends_on->toDateString()) {
            return false;
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Queries                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Word-prefix search over the award, the sponsor and the student. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('sponsor', 'like', "%{$term}%")
            ->orWhere('reference', 'like', "%{$term}%")
            ->orWhereHas('student', fn (Builder $s) => $s
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%")));
    }
}
