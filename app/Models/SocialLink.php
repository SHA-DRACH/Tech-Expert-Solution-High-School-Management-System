<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    use BelongsToSchool, HasFactory;

    public const PLATFORMS = ['Facebook', 'Instagram', 'X', 'YouTube', 'LinkedIn', 'WhatsApp', 'TikTok'];

    protected $fillable = ['school_id', 'platform', 'url', 'position'];
}
