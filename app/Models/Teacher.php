<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use BelongsToSchool, HasDocuments, HasFactory, RecordsAuditTrail, SoftDeletes;

    /** Which document types apply to this kind of record. */
    protected string $documentSubject = 'teachers';

    public const STATUSES = ['active', 'on_leave', 'suspended', 'resigned', 'retired'];

    protected string $auditModule = 'Teachers & staff';

    protected $fillable = [
        'school_id', 'user_id', 'department_id', 'staff_number', 'first_name', 'middle_name',
        'last_name', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'photo_path',
        'employment_type', 'hired_on', 'experience_years', 'biography', 'is_public', 'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'hired_on' => 'date', 'is_public' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(TeacherQualification::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' ');
    }

    /** Only what the school has approved for publication. */
    public function publicQualifications()
    {
        return $this->qualifications()->where('is_public', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return Search::apply($query, $term, [
            'words' => ['first_name', 'middle_name', 'last_name', 'email'],
            'identifiers' => ['staff_number', 'phone'],
        ]);
    }
}
