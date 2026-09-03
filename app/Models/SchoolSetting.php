<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
