<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = ['school_id', 'assessment_id', 'student_id', 'score', 'remark', 'recorded_by'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Score as a percentage of the assessment's maximum. */
    public function percentage(): ?float
    {
        $max = $this->assessment?->max_score;

        if (! $max || $this->score === null) {
            return null;
        }

        return round(((float) $this->score / $max) * 100, 2);
    }
}
