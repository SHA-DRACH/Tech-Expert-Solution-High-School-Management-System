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
        'teachers' => 'Teachers',
        'news' => 'News',
        'events' => 'Events',
        'gallery' => 'Gallery',
        'online-services' => 'Online services',
        'contact' => 'Contact',
    ];

    protected string $auditModule = 'Website';

    protected $fillable = [
        'school_id', 'key', 'title', 'slug', 'summary', 'sections',
        'hero_image_path', 'meta_description', 'is_published', 'position', 'texts',
    ];

    protected function casts(): array
    {
        return ['sections' => 'array', 'texts' => 'array', 'is_published' => 'boolean'];
    }

    /**
     * Make sure every built-in page has a row the school can edit.
     *
     * Only the title is stored; the introduction and wording stay empty so they
     * keep following the defaults in SiteContent until the school writes its own.
     */
    public static function ensureBuiltIn(School $school): void
    {
        $position = 1;

        foreach (\App\Support\SiteContent::PAGES as $key => $definition) {
            static::firstOrCreate(
                ['school_id' => $school->id, 'key' => $key],
                [
                    'title' => str_replace(':school', $school->name, $definition['title']),
                    'slug' => $definition['slug'],
                    'is_published' => true,
                    'position' => $position,
                ],
            );

            $position++;
        }
    }

    /** A page the school created itself, rather than one the website is built around. */
    public function isCustom(): bool
    {
        return str_starts_with($this->key, \App\Support\SiteContent::CUSTOM_PREFIX);
    }

    /** Where the page lives on the public website. */
    public function publicUrl(): string
    {
        return match (true) {
            $this->isCustom() => route('public.page', $this->slug),
            $this->key === 'home' => route('home'),
            $this->key === 'about' => route('public.about'),
            $this->key === 'academics' => route('public.academics'),
            $this->key === 'admissions' => route('public.admissions'),
            $this->key === 'teachers' => route('public.teachers'),
            $this->key === 'news' => route('public.news'),
            $this->key === 'events' => route('public.events'),
            $this->key === 'gallery' => route('public.gallery'),
            $this->key === 'online-services' => route('online.index'),
            $this->key === 'contact' => route('public.contact'),
            default => route('home'),
        };
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
