<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const METHODS = ['Cash', 'Mobile money', 'Bank transfer', 'Cheque', 'Other'];

    /**
     * Methods where the money moved somewhere the school cannot see.
     *
     * Cash is handed over the counter and the receipt is the proof. A bank
     * transfer, a mobile-money payment or a cheque happened elsewhere, and the
     * only thing tying it to this school is the slip the family brings in — so
     * that reference is required, not optional. Recording one without it is
     * recording a payment nobody can later trace.
     */
    public const METHODS_NEEDING_PROOF = ['Mobile money', 'Bank transfer', 'Cheque'];

    protected string $auditModule = 'Finance';

    protected $fillable = [
        'school_id', 'invoice_id', 'student_id', 'guardian_id', 'receipt_number',
        'amount_minor', 'method', 'reference', 'paid_on', 'received_by', 'note',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function auditLabel(): string
    {
        return '"'.$this->receipt_number.'"';
    }
}
