<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use App\Support\SchoolContext;

/**
 * Users are intentionally NOT covered by SchoolScope: authentication has to
 * find an account by email before any tenant is known. Tenant filtering for
 * user listings goes through scopeInCurrentSchool() instead.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, RecordsAuditTrail;

    public const SUPER_ADMINISTRATOR = 'super-administrator';

    protected string $auditModule = 'Users';

    protected array $auditIgnored = ['email_verified_at'];

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Permission slugs resolved once per request. */
    protected ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /** The staff record this login belongs to, where it represents a teacher. */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Which number the SMS channel should text.
     *
     * An account has no phone number of its own; the person behind it does, so
     * this reads through to their guardian or teacher record.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->guardianProfile?->phone
            ?? \App\Models\Teacher::where('user_id', $this->id)->value('phone');
    }

    public function isSuperAdministrator(): bool
    {
        return $this->hasRole(self::SUPER_ADMINISTRATOR);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /** @param  array<int, string>|string  $slugs */
    public function hasAnyRole(array|string $slugs): bool
    {
        return $this->roles->whereIn('slug', (array) $slugs)->isNotEmpty();
    }

    public function permissionSlugs(): Collection
    {
        return $this->permissionCache ??= $this->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdministrator() || $this->permissionSlugs()->contains($permission);
    }

    /** @param  array<int, string>  $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->isSuperAdministrator()
            || $this->permissionSlugs()->intersect($permissions)->isNotEmpty();
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /** Does this user belong to the school currently in context? */
    public function belongsToCurrentSchool(): bool
    {
        return $this->school_id !== null && $this->school_id === app(SchoolContext::class)->schoolId();
    }

    public function scopeInCurrentSchool(Builder $query): Builder
    {
        $context = app(SchoolContext::class);

        if ($context->isUnrestricted()) {
            return $query;
        }

        $schoolId = $context->schoolId();

        return $schoolId
            ? $query->where('users.school_id', $schoolId)
            : $query->whereRaw('1 = 0');
    }
}
