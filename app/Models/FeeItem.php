<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeItem extends Model
{
    use BelongsToSchool, HasFactory;

    public const CATEGORIES = [
        'Tuition', 'Registration', 'Examination', 'Library',
        'Laboratory', 'ICT', 'Sports', 'Other',
    ];

    protected $fillable = ['school_id', 'fee_structure_id', 'category', 'description', 'amount_minor'];

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }
}
