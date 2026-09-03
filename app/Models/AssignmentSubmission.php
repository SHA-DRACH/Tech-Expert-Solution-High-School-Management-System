<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'school_id', 'assessment_id', 'student_id', 'body',
        'attachment_path', 'original_name', 'submitted_at', 'status', 'teacher_note',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Handed in after the assessment closed. */
    public function isLate(): bool
    {
        // `ends_at` is a real moment, so no endOfDay() rounding: an assessment
        // that closes at 10:30 means 10:30, not midnight.
        $closes = $this->assessment?->ends_at;

        return $closes !== null && $this->submitted_at !== null && $this->submitted_at->gt($closes);
    }
}
