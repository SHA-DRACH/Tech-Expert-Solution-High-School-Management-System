<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherQualification extends Model
{
    use BelongsToSchool, HasFactory;

    public const TYPES = ['qualification', 'certification', 'development'];

    protected $fillable = [
        'school_id', 'teacher_id', 'type', 'title', 'institution',
        'field', 'awarded_year', 'authority', 'is_public',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
