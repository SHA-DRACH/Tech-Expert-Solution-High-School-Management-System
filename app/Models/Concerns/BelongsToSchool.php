<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\SchoolContext;

/**
 * Marks a model as tenant-owned. Applies SchoolScope to every query and stamps
 * the active school on new records so callers never have to remember to.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', app(SchoolContext::class)->schoolId());
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Query one specific school, bypassing the ambient context. Authorize before calling. */
    public function scopeForSchool(Builder $query, School|int $school): Builder
    {
        return $query->withoutGlobalScope(SchoolScope::class)
            ->where($this->getTable().'.school_id', $school instanceof School ? $school->id : $school);
    }

    /** Query across every school. Platform-level use only. */
    public function scopeAcrossSchools(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SchoolScope::class);
    }
}
