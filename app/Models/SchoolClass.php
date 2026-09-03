<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A grade level, for example "Grade 10". Named SchoolClass because `Class` is
 * a reserved word in PHP; the table is `school_classes`.
 */
class SchoolClass extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail, SoftDeletes;

    protected $table = 'school_classes';

    protected string $auditModule = 'Academics';

    protected $fillable = ['school_id', 'name', 'level', 'stage', 'description'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('name');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject')->withPivot('school_id')->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
