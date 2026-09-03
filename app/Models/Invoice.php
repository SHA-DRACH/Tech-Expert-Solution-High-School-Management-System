<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const STATUSES = ['draft', 'issued', 'part_paid', 'paid', 'cancelled'];

    protected string $auditModule = 'Finance';

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'term_id', 'invoice_number',
        'issued_on', 'due_on', 'total_minor', 'paid_minor', 'discount_minor', 'status', 'note',
    ];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function auditLabel(): string
    {
        return '"'.$this->invoice_number.'"';
    }

    public function balanceMinor(): int
    {
        return max(0, $this->total_minor - $this->discount_minor - $this->paid_minor);
    }

    public function isSettled(): bool
    {
        return $this->balanceMinor() === 0;
    }

    /** Recalculate paid amount and status from the payments on record. */
    public function refreshTotals(): void
    {
        $this->paid_minor = (int) $this->payments()->sum('amount_minor');

        $this->status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $this->balanceMinor() === 0 => 'paid',
            $this->paid_minor > 0 => 'part_paid',
            default => 'issued',
        };

        $this->save();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'part_paid']);
    }
}
