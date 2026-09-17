<?php

namespace App\Services;

use App\Models\SchoolSetting;
use App\Support\SchoolContext;

/**
 * The school's own preferences (spec section 57).
 *
 * Every value here is something a school genuinely decides for itself and
 * something the application actually reads. A setting nothing consumes is
 * worse than no setting at all: it reads as a promise the software does not
 * keep, so the catalogue below is deliberately short.
 *
 * Values live one row per key in `school_settings`, tenant-scoped like
 * everything else. `admission_document_types` keeps the key it has always had
 * because the public admission form and the public admissions page already
 * read that row directly.
 */
class SchoolSettings
{
    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_LIST = 'list';

    public const TYPE_WEEKDAYS = 'weekdays';

    /**
     * The groups the settings screens are built from.
     *
     * @var array<string, array<string, mixed>>
     */
    public const GROUPS = [
        'academics' => [
            'label' => 'Academics & attendance',
            'description' => 'How marks are judged, how the register is kept, and what a report card shows.',
            'permission' => 'settings.manage',
            'icon' => '◈',
            'settings' => [
                'grading_pass_mark' => [
                    'label' => 'Pass mark',
                    'hint' => 'The lowest score counted as a pass. Report cards and grade summaries use it.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 50,
                    'rules' => ['integer', 'min:1', 'max:100'],
                    'suffix' => '%',
                ],
                /*
                 | What each part of a period grade is marked out of. A period
                 | grade is the marks added up over the marks possible, so when
                 | these total 100 it is simply the sum - which is how a teacher
                 | already works it out by hand.
                 */
                'grading_max_period_test' => [
                    'label' => 'Period test is marked out of',
                    'hint' => 'The four parts of a period grade normally add up to 100.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 40,
                    'rules' => ['integer', 'min:1', 'max:100'],
                ],
                'grading_max_quiz' => [
                    'label' => 'Quiz is marked out of',
                    'hint' => 'Part of each period grade.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 20,
                    'rules' => ['integer', 'min:1', 'max:100'],
                ],
                'grading_max_assignment' => [
                    'label' => 'Assignment is marked out of',
                    'hint' => 'Part of each period grade.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 20,
                    'rules' => ['integer', 'min:1', 'max:100'],
                ],
                'grading_max_attendance' => [
                    'label' => 'Attendance is marked out of',
                    'hint' => 'Part of each period grade.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 20,
                    'rules' => ['integer', 'min:1', 'max:100'],
                ],
                'grading_max_semester_exam' => [
                    'label' => 'Semester examination is marked out of',
                    'hint' => 'Counts as one of four equal parts of the semester average.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 100,
                    'rules' => ['integer', 'min:1', 'max:200'],
                ],
                'attendance_school_days' => [
                    'label' => 'School days',
                    'hint' => 'Attendance percentages and absence reports only count these days.',
                    'type' => self::TYPE_WEEKDAYS,
                    'default' => [1, 2, 3, 4, 5],
                    'rules' => ['array', 'min:1'],
                ],
                'attendance_notify_guardians' => [
                    'label' => 'Tell guardians about an absence',
                    'hint' => 'Notify a guardian the same day their child is marked absent or late.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => true,
                ],
                'reportcard_show_position' => [
                    'label' => 'Show class position on report cards',
                    'hint' => 'Some schools rank students in the class; others deliberately do not.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => true,
                ],
                'reportcard_show_attendance' => [
                    'label' => 'Show attendance on report cards',
                    'hint' => 'Prints days present and absent for the term alongside the marks.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => true,
                ],
                'reportcard_remark' => [
                    'label' => 'Standing note on every report card',
                    'hint' => 'Printed at the foot of each card, beneath the class teacher’s own remark.',
                    'type' => self::TYPE_TEXT,
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
            ],
        ],

        'finance' => [
            'label' => 'Finance',
            'description' => 'Currency, invoice terms and what a receipt says.',
            'permission' => 'settings.manage',
            'icon' => '◎',
            'settings' => [
                'finance_currency' => [
                    'label' => 'Currency symbol',
                    'hint' => 'Shown against every amount. Liberian schools bill in L$ or US$.',
                    'type' => self::TYPE_STRING,
                    'default' => 'L$',
                    'rules' => ['string', 'max:8'],
                ],
                'finance_invoice_due_days' => [
                    'label' => 'Invoice due after',
                    'hint' => 'Used when invoices are raised without an explicit due date.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 30,
                    'rules' => ['integer', 'min:1', 'max:365'],
                    'suffix' => 'days',
                ],
                'finance_reminder_days_before' => [
                    'label' => 'Start reminding families',
                    'hint' => 'How far ahead of the due date the scheduled reminder goes out.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 7,
                    'rules' => ['integer', 'min:0', 'max:90'],
                    'suffix' => 'days before',
                ],
                'finance_reminder_cooldown_days' => [
                    'label' => 'Wait between reminders',
                    'hint' => 'A family is not chased about the same invoice again inside this window.',
                    'type' => self::TYPE_INTEGER,
                    'default' => 7,
                    'rules' => ['integer', 'min:1', 'max:90'],
                    'suffix' => 'days',
                ],
                'finance_receipt_footer' => [
                    'label' => 'Receipt footer',
                    'hint' => 'Printed at the bottom of every receipt, for bank details or a thank-you.',
                    'type' => self::TYPE_TEXT,
                    'default' => '',
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
            ],
        ],

        'admissions' => [
            'label' => 'Admissions',
            'description' => 'Whether applications are open, and what families are asked to bring.',
            'permission' => 'settings.manage',
            'icon' => '✦',
            'settings' => [
                'admissions_open' => [
                    'label' => 'Applications are open',
                    'hint' => 'Closing this takes the public application form offline and says so politely.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => true,
                ],
                'admissions_closed_message' => [
                    'label' => 'Message when applications are closed',
                    'hint' => 'Shown in place of the form. Say when you expect to reopen.',
                    'type' => self::TYPE_TEXT,
                    'default' => 'Applications are closed at the moment. Please contact the school office to be told when they reopen.',
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
                /*
                 | This key predates the settings screen: the public application
                 | form and the public admissions page both read this exact row
                 | already. Renaming it would silently reset every school's list
                 | back to the defaults, so it keeps its original name.
                 */
                'admission_document_types' => [
                    'label' => 'Documents an applicant must provide',
                    'hint' => 'One per line. These become the upload slots on the application form.',
                    'type' => self::TYPE_LIST,
                    'default' => ['Birth certificate', 'Previous report card', 'Passport photograph'],
                    'rules' => ['array', 'min:1', 'max:20'],
                ],
            ],
        ],

        'online' => [
            'label' => 'Online services',
            'description' => 'What the public Online services page offers, and what it tells families.',
            'permission' => 'settings.manage',
            'icon' => '◎',
            'settings' => [
                /*
                 | Checking by student ID needs a number printed on the child's
                 | own papers. Checking by name and class needs only a name, so
                 | it tells anyone which class a child is in - useful to an
                 | employer or another school, and a school may decide that is
                 | more than it wants public.
                 */
                'online_lookup_by_name' => [
                    'label' => 'Allow checking a student by name and class',
                    'hint' => 'Checking by student ID is always available. Turn this off to require the ID.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => true,
                ],
                'online_payment_instructions' => [
                    'label' => 'How to pay fees',
                    'hint' => 'Shown on the Online services page: bank account, mobile money number, what to bring.',
                    'type' => self::TYPE_TEXT,
                    'default' => "Pay fees at the bank or by mobile money, then bring the bank slip or the transaction message to the school's finance office. Your payment is recorded and a receipt issued once the slip is presented.",
                    'rules' => ['nullable', 'string', 'max:1000'],
                ],
                'online_office_hours' => [
                    'label' => 'Office hours',
                    'hint' => 'When families can reach the office.',
                    'type' => self::TYPE_STRING,
                    'default' => 'Monday to Friday, 8:00 AM – 4:00 PM',
                    'rules' => ['nullable', 'string', 'max:120'],
                ],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'description' => 'What reaches a parent’s phone as well as their portal.',
            'permission' => 'settings.manage',
            'icon' => '✉',
            'settings' => [
                'sms_attendance_alerts' => [
                    'label' => 'Send absence alerts by SMS',
                    'hint' => 'In-app notifications are always sent; this adds a text message.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => false,
                ],
                'sms_fee_reminders' => [
                    'label' => 'Send fee reminders by SMS',
                    'hint' => 'In-app notifications are always sent; this adds a text message.',
                    'type' => self::TYPE_BOOLEAN,
                    'default' => false,
                ],
            ],
        ],
    ];

    public function __construct(private readonly SchoolContext $context) {}

    /** Every setting key in the catalogue, mapped to its definition. */
    public static function catalogue(): array
    {
        $flat = [];

        foreach (self::GROUPS as $group) {
            foreach ($group['settings'] as $key => $definition) {
                $flat[$key] = $definition;
            }
        }

        return $flat;
    }

    /** @return array<string, mixed> the definitions in one group */
    public static function group(string $name): array
    {
        return self::GROUPS[$name] ?? throw new \InvalidArgumentException("Unknown settings group [{$name}].");
    }

    /**
     * Every setting, resolved against its default.
     *
     * Deliberately not memoised, for the reason recorded on PublicVisibility:
     * Laravel caches a controller on its Route, so anything cached on an
     * injected service outlives the request that built it and an administrator
     * can save a change that appears to do nothing. One query is cheap; a
     * setting that silently refuses to change is not.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = SchoolSetting::query()
            ->whereIn('key', array_keys(self::catalogue()))
            ->pluck('value', 'key');

        $resolved = [];

        foreach (self::catalogue() as $key => $definition) {
            $resolved[$key] = $stored->has($key)
                ? $this->cast($stored->get($key), $definition)
                : $definition['default'];
        }

        return $resolved;
    }

    /** One setting, resolved against its default. */
    public function get(string $key): mixed
    {
        $definition = self::catalogue()[$key]
            ?? throw new \InvalidArgumentException("Unknown setting [{$key}].");

        $row = SchoolSetting::where('key', $key)->first();

        return $row === null ? $definition['default'] : $this->cast($row->value, $definition);
    }

    /**
     * Save one group.
     *
     * Only the keys belonging to `$group` are touched. An unticked checkbox is
     * not submitted at all, so a form that saved every key it did not mention
     * would quietly switch off settings on the other tabs.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed> the settings that actually changed, for the audit trail
     */
    public function updateGroup(int $schoolId, string $group, array $values): array
    {
        $definitions = self::group($group)['settings'];
        $before = $this->all();
        $changed = [];

        foreach ($definitions as $key => $definition) {
            $value = $this->normalise($values[$key] ?? null, $definition);

            if ($before[$key] === $value) {
                continue;
            }

            SchoolSetting::updateOrCreate(
                ['school_id' => $schoolId, 'key' => $key],
                ['value' => $value],
            );

            $changed[$key] = $value;
        }

        return $changed;
    }

    /** Validation rules for one group's form, keyed by request field. */
    public function rulesFor(string $group): array
    {
        $rules = [];

        foreach (self::group($group)['settings'] as $key => $definition) {
            $field = "settings.{$key}";

            $rules[$field] = match ($definition['type']) {
                // A checkbox that is off is simply absent from the request.
                self::TYPE_BOOLEAN => ['nullable'],
                self::TYPE_LIST, self::TYPE_WEEKDAYS => ['nullable'],
                default => array_merge(['nullable'], $definition['rules'] ?? []),
            };

            if ($definition['type'] === self::TYPE_WEEKDAYS) {
                $rules["{$field}.*"] = ['integer', 'between:1,7'];
            }

            if ($definition['type'] === self::TYPE_LIST) {
                $rules[$field] = ['nullable', 'string', 'max:2000'];
            }
        }

        return $rules;
    }

    /** Turn a stored value back into the shape the rest of the app expects. */
    protected function cast(mixed $value, array $definition): mixed
    {
        return match ($definition['type']) {
            self::TYPE_BOOLEAN => (bool) $value,
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_STRING, self::TYPE_TEXT => (string) $value,
            self::TYPE_LIST => array_values(array_filter((array) $value, fn ($line) => filled($line))),
            self::TYPE_WEEKDAYS => array_values(array_map('intval', (array) $value)),
            default => $value,
        };
    }

    /** Turn what a form submitted into what gets stored. */
    protected function normalise(mixed $submitted, array $definition): mixed
    {
        return match ($definition['type']) {
            self::TYPE_BOOLEAN => (bool) $submitted,
            self::TYPE_INTEGER => $submitted === null || $submitted === ''
                ? $definition['default']
                : (int) $submitted,
            self::TYPE_STRING, self::TYPE_TEXT => (string) ($submitted ?? ''),
            // A textarea, one entry per line.
            self::TYPE_LIST => $this->lines($submitted, $definition),
            self::TYPE_WEEKDAYS => $this->weekdays($submitted, $definition),
            default => $submitted,
        };
    }

    protected function lines(mixed $submitted, array $definition): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', (string) $submitted))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // An empty list would leave the application form with no upload slots
        // at all, so the school's own defaults stand instead.
        return $lines === [] ? $definition['default'] : $lines;
    }

    protected function weekdays(mixed $submitted, array $definition): array
    {
        $days = collect((array) $submitted)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        // A school with no school days would divide by zero in every
        // attendance percentage on the platform.
        return $days === [] ? $definition['default'] : $days;
    }
}
