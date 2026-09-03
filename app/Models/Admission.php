<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    use HasFactory, BelongsToSchool, RecordsAuditTrail;

    public const STATUSES = [
        'draft', 'submitted', 'pending', 'under_review', 'documents_required',
        'interview_required', 'approved', 'rejected', 'enrolled', 'cancelled',
    ];

    protected string $auditModule = 'Admissions';

    protected $fillable = [
        'school_id', 'application_number', 'status',
        'student_first_name', 'student_middle_name', 'student_last_name',
        'gender', 'date_of_birth', 'place_of_birth', 'nationality',
        'previous_school', 'previous_class', 'intended_class', 'academic_year',
        'guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_email',
        'guardian_address', 'guardian_occupation', 'emergency_contact', 'review_notes',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function getStudentNameAttribute(): string
    {
        return collect([$this->student_first_name, $this->student_middle_name, $this->student_last_name])
            ->filter()->implode(' ');
    }

    public function auditLabel(): string
    {
        return '"'.$this->application_number.'"';
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('application_number', 'like', "%{$term}%")
            ->orWhere('student_first_name', 'like', "%{$term}%")
            ->orWhere('student_last_name', 'like', "%{$term}%")
            ->orWhere('guardian_name', 'like', "%{$term}%"));
    }
}
