<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Finance';

    protected $fillable = [
        'school_id', 'academic_year_id', 'school_class_id', 'term_id',
        'name', 'description', 'is_active',
        'document_path', 'document_name', 'document_on_website',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'document_on_website' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeItem::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function hasDocument(): bool
    {
        return filled($this->document_path);
    }

    /**
     * Active structures with a PDF that apply to a student: those for the
     * student's own class, and those for the whole school.
     */
    public static function documentsFor(?Student $student): \Illuminate\Support\Collection
    {
        $classId = $student?->currentEnrollment?->section?->school_class_id
            ?? $student?->currentEnrollment?->school_class_id;

        return static::query()
            ->where('is_active', true)
            ->whereNotNull('document_path')
            ->where(fn ($q) => $q->whereNull('school_class_id')->when($classId, fn ($q) => $q->orWhere('school_class_id', $classId)))
            ->with(['schoolClass:id,name', 'term:id,name'])
            ->orderBy('name')
            ->get();
    }

    /** Active structures whose PDF the school chose to publish on the website. */
    public static function publicDocuments(): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where('document_on_website', true)
            ->whereNotNull('document_path')
            ->with(['schoolClass:id,name', 'term:id,name'])
            ->orderBy('name')
            ->get();
    }

    public function totalMinor(): int
    {
        return (int) $this->items()->sum('amount_minor');
    }
}
