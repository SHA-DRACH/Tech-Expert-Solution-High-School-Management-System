<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail, SoftDeletes;

    public const STATUSES = ['pending', 'verified', 'rejected', 'requires_correction'];

    protected string $auditModule = 'Documents';

    /** The storage path is never written to the audit trail. */
    protected array $auditIgnored = ['path'];

    protected $fillable = [
        'school_id', 'documentable_type', 'documentable_id', 'document_type_id',
        'title', 'path', 'original_name', 'mime_type', 'size_bytes',
        'status', 'review_note', 'issued_on', 'expires_on',
        'uploaded_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function auditLabel(): string
    {
        return '"'.$this->title.'"';
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    /** Within a month of expiring, so the office can chase a replacement. */
    public function isExpiringSoon(): bool
    {
        return $this->expires_on !== null
            && ! $this->isExpired()
            && $this->expires_on->lte(now()->addMonth());
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        return match (true) {
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }

    public function scopeExpiring(Builder $query): Builder
    {
        return $query->whereNotNull('expires_on')->where('expires_on', '<=', now()->addMonth());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', "%{$term}%")
            ->orWhere('original_name', 'like', "%{$term}%"));
    }
}
