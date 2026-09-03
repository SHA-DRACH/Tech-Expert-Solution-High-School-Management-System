<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardItem extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = ['school_id', 'report_card_id', 'subject_id', 'score', 'grade', 'remark', 'position'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
