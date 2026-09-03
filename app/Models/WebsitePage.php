<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsitePage extends Model
{
    use BelongsToSchool, HasFactory, RecordsAuditTrail;

    /** The pages the public website expects to exist. */
    public const KEYS = [
        'home' => 'Home',
        'about' => 'About us',
        'academics' => 'Academics',
        'admissions' => 'Admissions',
        'contact' => 'Contact',
    ];

    protected string $auditModule = 'Website';

    protected $fillable = [
        'school_id', 'key', 'title', 'slug', 'summary', 'sections',
        'hero_image_path', 'meta_description', 'is_published', 'position',
    ];

    protected function casts(): array
    {
        return ['sections' => 'array', 'is_published' => 'boolean'];
    }

    public function auditLabel(): string
    {
        return '"'.$this->title.'"';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Content blocks with anything empty dropped, so a half-filled row never
     * renders as a blank band on the public page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blocks(): array
    {
        return collect($this->sections ?? [])
            ->filter(fn ($section) => filled($section['heading'] ?? null) || filled($section['body'] ?? null))
            ->values()
            ->all();
    }
}
