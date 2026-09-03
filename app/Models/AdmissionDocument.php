<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDocument extends Model
{
    use BelongsToSchool, RecordsAuditTrail;

    public const STATUSES = ['pending', 'under_review', 'verified', 'rejected', 'requires_correction'];

    protected string $auditModule = 'Admission documents';

    protected $fillable = [
        'school_id', 'admission_id', 'document_type', 'original_name', 'path',
        'status', 'review_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** Never expose the storage path in the audit trail or API payloads. */
    protected array $auditIgnored = ['path'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function auditLabel(): string
    {
        return '"'.$this->document_type.'"';
    }
}
