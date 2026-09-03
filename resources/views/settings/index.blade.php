@php
    use App\Services\SchoolSettings;

    $user = auth()->user();

    /*
     | Everything a school configures, in one place. Entries that live on
     | another screen are linked rather than duplicated: the grading scale sits
     | with examinations because that is where it is used, and moving it would
     | break links people already have. Each carries the permission that reveals
     | it, so an accountant and a registrar see different hubs.
     */
    $cards = collect([
        [
            'label' => 'School profile & branding',
            'description' => 'Name, motto, contact details, colours and logo.',
            'icon' => '⚙',
            'route' => route('settings.school.edit'),
            'can' => 'settings.manage',
            'value' => $school->name,
        ],
        [
            'label' => 'Academic years & terms',
            'description' => 'Open a new year, set its terms, and move the school into the term it is teaching.',
            'icon' => '▤',
            'route' => route('settings.years.index'),
            'can' => 'academics.manage',
            'value' => $currentYear
                ? $currentYear->name.($currentTerm ? ' · '.$currentTerm->name : ' · no current term')
                : 'No academic year yet',
            'warn' => $currentYear === null || $currentTerm === null,
        ],
    ]);

    foreach (SchoolSettings::GROUPS as $key => $group) {
        $cards->push([
            'label' => $group['label'],
            'description' => $group['description'],
            'icon' => $group['icon'],
            'route' => route('settings.group.edit', $key),
            'can' => $group['permission'],
            'value' => match ($key) {
                'academics' => 'Pass mark '.$values['grading_pass_mark'].'%',
                'finance' => 'Billing in '.$values['finance_currency'],
                'admissions' => $values['admissions_open'] ? 'Applications open' : 'Applications closed',
                'notifications' => $values['sms_attendance_alerts'] || $values['sms_fee_reminders']
                    ? 'SMS on for some alerts'
                    : 'In-app notifications only',
                default => null,
            },
        ]);
    }

    $elsewhere = collect([
        [
            'label' => 'Grading scale',
            'description' => 'The bands that turn a score into a letter grade.',
            'route' => route('examinations.index'),
            'can' => 'exams.manage',
        ],
        [
            'label' => 'Roles & permissions',
            'description' => 'What each kind of account is allowed to do.',
            'route' => route('roles.index'),
            'can' => 'roles.view',
        ],
        [
            'label' => 'Student portal access',
            'description' => 'Which parts of the portal students can open.',
            'route' => route('students.permissions'),
            'can' => 'students.update',
        ],
        [
            'label' => 'What the public website shows',
            'description' => 'The sections published on your public site.',
            'route' => route('website.index'),
            'can' => 'website.manage',
        ],
        [
            'label' => 'Document types',
            'description' => 'The document categories student records accept.',
            'route' => route('documents.index'),
            'can' => 'settings.manage',
        ],
    ])->filter(fn (array $item) => $user->hasPermission($item['can']));

    $cards = $cards->filter(fn (array $item) => $user->hasPermission($item['can']));
@endphp

<x-layouts.app title="Settings" heading="Settings">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Settings' => null]" />

    <x-ui.page-header
        title="Settings"
        description="Everything {{ $school->name }} decides for itself."
    />

    @if ($currentYear === null || $currentTerm === null)
        <div class="enter-rise mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="text-sm font-semibold text-amber-900">
                {{ $currentYear === null ? 'No academic year has been set up.' : 'No current term has been set.' }}
            </p>
            <p class="mt-1 text-sm text-amber-800">
                Enrolment, attendance, assessments, report cards and invoices are all filed against a year and a
                term. Until both are set, those screens have nothing to file against.
            </p>
            <a href="{{ route('settings.years.index') }}"
               class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-900 underline underline-offset-2">
                Set up the academic calendar
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            <a href="{{ $card['route'] }}"
               class="lift group flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm enter-rise"
               style="animation-delay: {{ $loop->index * 40 }}ms">
                <span class="icon-badge" aria-hidden="true">{{ $card['icon'] }}</span>

                <h2 class="mt-4 text-sm font-semibold text-slate-900">{{ $card['label'] }}</h2>
                <p class="mt-1 flex-1 text-sm text-slate-500">{{ $card['description'] }}</p>

                @if (! empty($card['value']))
                    <p @class([
                        'mt-4 truncate text-xs font-medium',
                        'text-amber-700' => $card['warn'] ?? false,
                        'text-slate-600' => ! ($card['warn'] ?? false),
                    ])>{{ $card['value'] }}</p>
                @endif
            </a>
        @endforeach
    </div>

    @if ($elsewhere->isNotEmpty())
        <x-ui.section-heading
            class="mt-10"
            title="Configured elsewhere"
            description="These belong with the screens that use them, so this is where they live."
        />

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($elsewhere as $item)
                <a href="{{ $item['route'] }}"
                   class="group flex items-start justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-brand/40 hover:bg-slate-50">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-slate-800">{{ $item['label'] }}</span>
                        <span class="mt-0.5 block text-xs text-slate-500">{{ $item['description'] }}</span>
                    </span>
                    <span aria-hidden="true"
                          class="mt-0.5 shrink-0 text-slate-300 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:text-brand">&rarr;</span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
