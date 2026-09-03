<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const AUDIENCES = ['parents', 'students', 'teachers', 'staff', 'public'];

    public const CATEGORIES = ['general', 'fees', 'examination', 'emergency', 'event'];

    protected string $auditModule = 'Communication';

    protected $fillable = [
        'school_id', 'created_by', 'section_id', 'title', 'body', 'category', 'audience',
        'image_path', 'attachment_path', 'published_at', 'expires_at', 'status', 'is_emergency',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_emergency' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Published, not yet expired. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    /** Announcements aimed at a particular portal. */
    public function scopeFor(Builder $query, string $audience): Builder
    {
        return $query->whereJsonContains('audience', $audience);
    }
}
