<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const CATEGORIES = ['pta', 'examination', 'graduation', 'sports', 'workshop', 'celebration', 'academic'];

    protected string $auditModule = 'Communication';

    protected $fillable = [
        'school_id', 'created_by', 'title', 'description', 'category',
        'starts_at', 'ends_at', 'location', 'is_public', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_public' => 'boolean'];
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }
}
