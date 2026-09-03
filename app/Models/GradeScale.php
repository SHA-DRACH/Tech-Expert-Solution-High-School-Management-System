<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A school's own grade boundaries. Nothing about grading is hard-coded: the
 * letter awarded for a score is whatever this table says it is.
 */
class GradeScale extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Examinations & grades';

    protected $fillable = ['school_id', 'grade', 'min_score', 'max_score', 'remark', 'points', 'sequence'];

    /** @return array{grade: string, remark: ?string}|null */
    public static function forScore(float $score): ?self
    {
        return static::query()
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->orderBy('sequence')
            ->first();
    }
}
