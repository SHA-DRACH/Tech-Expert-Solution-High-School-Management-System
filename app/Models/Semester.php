<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Half of a school year: three marking periods and an examination.
 *
 * It exists as a record, rather than being worked out from period numbers, for
 * one reason - the examination window. Whether a teacher may enter semester exam
 * marks is an administrative decision taken on a particular day, and it has to
 * be recorded somewhere, together with who took it.
 */
class Semester extends Model
{
    use BelongsToSchool, RecordsAuditTrail;

    /** Three marking periods to a semester. */
    public const PERIODS_PER_SEMESTER = 3;

    protected string $auditModule = 'Academics';

    protected $fillable = [
        'school_id', 'academic_year_id', 'number', 'name',
        'exam_starts_on', 'exam_ends_on',
        'exam_entry_open', 'exam_entry_opened_at', 'exam_entry_opened_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_starts_on' => 'date',
            'exam_ends_on' => 'date',
            'exam_entry_open' => 'boolean',
            'exam_entry_opened_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exam_entry_opened_by');
    }

    /** The marking periods that make up this semester. */
    public function periods(): HasMany
    {
        return $this->hasMany(Term::class, 'academic_year_id', 'academic_year_id')
            ->where('semester', $this->number)
            ->orderBy('sequence');
    }

    public function auditLabel(): string
    {
        return '"'.$this->name.'"';
    }

    /** "First semester" / "Second semester". */
    public static function nameFor(int $number): string
    {
        return match ($number) {
            1 => 'First semester',
            2 => 'Second semester',
            default => 'Semester '.$number,
        };
    }
}
