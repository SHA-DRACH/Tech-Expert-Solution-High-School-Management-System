<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    protected string $auditModule = 'Finance';

    protected $fillable = [
        'school_id', 'academic_year_id', 'category', 'description',
        'amount_minor', 'spent_on', 'reference', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['spent_on' => 'date'];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function auditLabel(): string
    {
        return '"'.$this->description.'"';
    }
}
