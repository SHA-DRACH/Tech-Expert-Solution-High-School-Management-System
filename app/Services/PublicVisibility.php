<?php

namespace App\Services;

use App\Models\SchoolSetting;

/**
 * What a school chooses to publish (spec sections 10 and 11).
 *
 * The spec is explicit that the administrator decides what appears publicly —
 * subject lists, the academic calendar, and fees especially. Everything here is
 * a stored setting with a sensible default, so a new school has a complete site
 * without having to answer a dozen questions first.
 */
class PublicVisibility
{
    protected const SETTING_KEY = 'public_visibility';

    /**
     * Each switch, its label for the settings screen, and whether a school
     * starts with it on.
     *
     * Fees default to hidden: publishing them is a commercial decision and
     * should be a deliberate act, not something a school discovers by accident.
     *
     * @var array<string, array{label: string, help: string, default: bool}>
     */
    public const SWITCHES = [
        'teachers' => [
            'label' => 'Teaching staff',
            'help' => 'Show the Teachers page. Only staff whose profile is published appear.',
            'default' => true,
        ],
        'news' => [
            'label' => 'News',
            'help' => 'Show the News page and the latest posts on the homepage.',
            'default' => true,
        ],
        'events' => [
            'label' => 'Events',
            'help' => 'Show the Events page and upcoming events on the homepage.',
            'default' => true,
        ],
        'gallery' => [
            'label' => 'Gallery',
            'help' => 'Show the photograph gallery.',
            'default' => true,
        ],
        'announcements' => [
            'label' => 'Announcements',
            'help' => 'Show announcements marked for the public on the homepage.',
            'default' => true,
        ],
        'statistics' => [
            'label' => 'School statistics',
            'help' => 'Show student, teacher and class numbers on the homepage.',
            'default' => true,
        ],
        'departments' => [
            'label' => 'Departments',
            'help' => 'List academic departments on the Academics page.',
            'default' => true,
        ],
        'subjects' => [
            'label' => 'Subjects',
            'help' => 'List the subjects taught on the Academics page.',
            'default' => true,
        ],
        'calendar' => [
            'label' => 'Academic calendar',
            'help' => 'Show term dates on the Academics page.',
            'default' => true,
        ],
        'classes' => [
            'label' => 'Available classes',
            'help' => 'Show which classes accept applications on the Admissions page.',
            'default' => true,
        ],
        'fees' => [
            'label' => 'School fees',
            'help' => 'Publish fee amounts on the Admissions page. Off unless you choose to.',
            'default' => false,
        ],
    ];

    public function shows(string $switch): bool
    {
        return $this->all()[$switch] ?? false;
    }

    /**
     * @return array<string, bool>
     *
     * Deliberately not memoised. An earlier version cached the resolved set on
     * the instance, and because Laravel caches a controller on its Route, that
     * cache outlived the request: an administrator could switch a section off
     * and the page would keep serving until the process restarted. One small
     * query is the right price for a switch that means "stop publishing this".
     */
    public function all(): array
    {
        $stored = SchoolSetting::where('key', self::SETTING_KEY)->first()?->value ?? [];

        return collect(self::SWITCHES)
            ->map(fn (array $definition, string $key) => array_key_exists($key, $stored)
                ? (bool) $stored[$key]
                : $definition['default'])
            ->all();
    }

    /** @param  array<string, mixed>  $switches */
    public function update(int $schoolId, array $switches): void
    {
        $value = collect(self::SWITCHES)
            ->map(fn (array $definition, string $key) => (bool) ($switches[$key] ?? false))
            ->all();

        SchoolSetting::updateOrCreate(
            ['school_id' => $schoolId, 'key' => self::SETTING_KEY],
            ['value' => $value],
        );
    }
}
