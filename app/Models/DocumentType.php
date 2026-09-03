<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The kinds of document a school files. Configurable per school, because what
 * a school asks for is its own decision (spec sections 14 and 42).
 */
class DocumentType extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    public const SUBJECTS = [
        'students' => 'Students',
        'teachers' => 'Teachers',
        'staff' => 'Staff',
        'general' => 'General',
    ];

    protected string $auditModule = 'Documents';

    protected $fillable = [
        'school_id', 'name', 'applies_to', 'description',
        'is_required', 'expires', 'position',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'expires' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** Types that apply to a given kind of person, plus the general ones. */
    public function scopeFor(Builder $query, string $subject): Builder
    {
        return $query->whereIn('applies_to', [$subject, 'general'])->orderBy('position')->orderBy('name');
    }
}
