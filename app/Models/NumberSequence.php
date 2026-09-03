<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'sequence_key', 'period', 'next_value'];

    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }
}
