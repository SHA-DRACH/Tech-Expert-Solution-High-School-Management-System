<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail, SoftDeletes;

    protected string $auditModule = 'Parents & guardians';

    protected $fillable = [
        'school_id', 'user_id', 'first_name', 'last_name', 'phone', 'email',
        'occupation', 'address', 'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['relationship', 'is_primary', 'can_view_academics', 'can_view_finance'])
            ->withTimestamps();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ParentRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /** Is this guardian linked to the given student, and cleared for academics? */
    public function canViewAcademicsFor(Student $student): bool
    {
        $link = $this->students()->where('students.id', $student->id)->first();

        return $link !== null && (bool) $link->pivot->can_view_academics;
    }

    public function canViewFinanceFor(Student $student): bool
    {
        $link = $this->students()->where('students.id', $student->id)->first();

        return $link !== null && (bool) $link->pivot->can_view_finance;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                // Searching a parent by one of their children, as the spec asks.
                ->orWhereHas('students', fn (Builder $s) => $s
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('student_number', 'like', "%{$term}%"));
        });
    }
}
