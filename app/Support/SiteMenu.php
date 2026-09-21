<?php

namespace App\Support;

use App\Models\SchoolSetting;
use App\Models\WebsitePage;
use App\Services\PublicVisibility;
use Illuminate\Support\Collection;

/**
 * The public website's menu: which links appear, what they are called, and in
 * what order - the school's own choice, edited in Website › Menu & footer.
 *
 * Each item points at a page (built in, or one the school wrote) or at any
 * address the school types in. A built-in section the school has switched off
 * in "What is published" never appears, whatever the menu says, so hiding a
 * section cannot leave a dead link behind.
 */
class SiteMenu
{
    public const SETTING = 'website_menu';

    public const FOOTER_SETTING = 'website_footer_text';

    public const MAX_ITEMS = 15;

    /** Built-in pages: key => [route name, active pattern, visibility switch or null]. */
    public const BUILT_IN = [
        'home' => ['home', 'home', null],
        'about' => ['public.about', 'public.about', null],
        'academics' => ['public.academics', 'public.academics', null],
        'admissions' => ['public.admissions', 'public.admissions', null],
        'teachers' => ['public.teachers', 'public.teachers', 'teachers'],
        'news' => ['public.news', 'public.news*', 'news'],
        'events' => ['public.events', 'public.events', 'events'],
        'gallery' => ['public.gallery', 'public.gallery', 'gallery'],
        'online-services' => ['online.index', 'online.*', null],
        'contact' => ['public.contact', 'public.contact', null],
    ];

    /** The menu a school starts with. */
    public static function defaults(): array
    {
        $labels = [
            'home' => 'Home', 'about' => 'About', 'academics' => 'Academics', 'admissions' => 'Admissions',
            'teachers' => 'Teachers', 'news' => 'News', 'events' => 'Events', 'gallery' => 'Gallery',
            'online-services' => 'Online services', 'contact' => 'Contact',
        ];

        return collect($labels)->map(fn (string $label, string $key) => [
            'label' => $label, 'target' => $key, 'url' => null, 'visible' => true,
        ])->values()->all();
    }

    /** What the school saved, or the defaults. */
    public static function saved(): array
    {
        $value = SchoolSetting::where('key', self::SETTING)->value('value');

        return is_array($value) && $value !== [] ? $value : self::defaults();
    }

    /**
     * The menu as the public site renders it.
     *
     * @return Collection<int, array{label: string, url: string, active: string|null, external: bool}>
     */
    public static function links(PublicVisibility $visibility): Collection
    {
        $custom = WebsitePage::published()
            ->where('key', 'like', SiteContent::CUSTOM_PREFIX.'%')
            ->get()
            ->keyBy('key');

        return collect(self::saved())
            ->filter(fn (array $item) => ($item['visible'] ?? true) && filled($item['label'] ?? null))
            ->map(function (array $item) use ($visibility, $custom) {
                $target = $item['target'] ?? '';

                if (isset(self::BUILT_IN[$target])) {
                    [$route, $active, $switch] = self::BUILT_IN[$target];

                    if ($switch !== null && ! $visibility->shows($switch)) {
                        return null;
                    }

                    return ['label' => $item['label'], 'url' => route($route), 'active' => $active, 'external' => false];
                }

                if (str_starts_with($target, SiteContent::CUSTOM_PREFIX)) {
                    $page = $custom->get($target);

                    // A deleted or unpublished page drops out of the menu.
                    return $page ? [
                        'label' => $item['label'],
                        'url' => route('public.page', $page->slug),
                        'active' => null,
                        'external' => false,
                    ] : null;
                }

                if ($target === 'url' && self::safeUrl($item['url'] ?? null)) {
                    return [
                        'label' => $item['label'],
                        'url' => $item['url'],
                        'active' => null,
                        'external' => str_starts_with($item['url'], 'http'),
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /** Pages a menu item may point at: key => title. */
    public static function targets(): array
    {
        $builtIn = collect(WebsitePage::KEYS);

        $custom = WebsitePage::where('key', 'like', SiteContent::CUSTOM_PREFIX.'%')
            ->orderBy('title')
            ->pluck('title', 'key');

        return $builtIn->merge($custom)->all();
    }

    /**
     * Only addresses a visitor can safely follow: a path on this site, or a
     * web address. Never "javascript:" or anything else a browser would run.
     */
    public static function safeUrl(?string $url): bool
    {
        if (! filled($url)) {
            return false;
        }

        return (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
            || (bool) preg_match('#^https?://[^\s]+$#i', $url);
    }

    public static function footerText(): ?string
    {
        $value = SchoolSetting::where('key', self::FOOTER_SETTING)->value('value');

        return filled($value) ? (string) $value : null;
    }
}
