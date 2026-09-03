<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Like User, roles sit outside SchoolScope: the tenant-resolution middleware
 * has to read a user's roles before it knows which school to scope to.
 * Tenant filtering for role management goes through scopeInCurrentSchool().
 *
 * A null school_id marks a platform-level role (super administrator).
 */
class Role extends Model
{
    use RecordsAuditTrail;

    protected string $auditModule = 'Roles & permissions';

    protected $fillable = ['school_id', 'name', 'slug', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function isPlatformRole(): bool
    {
        return $this->school_id === null;
    }

    /** System roles keep their slug and cannot be deleted; their permissions stay editable. */
    public function isProtected(): bool
    {
        return $this->is_system;
    }

    public function scopeInCurrentSchool(Builder $query): Builder
    {
        $context = app(SchoolContext::class);

        if ($context->isUnrestricted()) {
            return $query;
        }

        $schoolId = $context->schoolId();

        return $schoolId
            ? $query->where('roles.school_id', $schoolId)
            : $query->whereRaw('1 = 0');
    }
}
