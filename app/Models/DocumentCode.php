<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The verification code printed on a grade sheet or report card.
 *
 * Named to avoid App\Services\DocumentCode, which draws QR codes: this is the
 * record, that is the picture.
 */
class DocumentCode extends Model
{
    use BelongsToSchool;

    public const GRADE_SHEET = 'grade-sheet';

    public const REPORT_CARD = 'report-card';

    /** No 0/O, 1/I/L: the code is read off paper and typed in by hand. */
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'school_id', 'code', 'type', 'student_id', 'academic_year_id', 'term_id',
        'times_checked', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return ['last_checked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** The code for a document, created the first time it is printed. */
    public static function for(string $type, Student $student, AcademicYear $year, ?Term $term = null): self
    {
        $existing = static::where('type', $type)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->where('term_id', $term?->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        do {
            $code = static::generate($type);
        } while (static::withoutGlobalScopes()->where('code', $code)->exists());

        return static::create([
            'school_id' => $student->school_id,
            'code' => $code,
            'type' => $type,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'term_id' => $term?->id,
        ]);
    }

    /** "GS-7K3P-X9QA": a prefix a person can recognise, eight random characters. */
    public static function generate(string $type): string
    {
        $pick = fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        $chars = implode('', array_map(fn () => $pick(), range(1, 8)));

        return ($type === self::GRADE_SHEET ? 'GS' : 'RC').'-'.substr($chars, 0, 4).'-'.substr($chars, 4, 4);
    }

    /** Whatever someone typed, in the stored form: "gs 7k3p x9qa" → "GS-7K3P-X9QA". */
    public static function normalise(string $input): string
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));

        if (strlen($raw) !== 10) {
            return $raw;
        }

        return substr($raw, 0, 2).'-'.substr($raw, 2, 4).'-'.substr($raw, 6, 4);
    }
}
