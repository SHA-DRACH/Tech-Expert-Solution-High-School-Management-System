<x-layouts.app title="Overview" heading="Overview">
    <x-ui.page-header
        :title="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.Str::before(auth()->user()->name, ' ')"
        :description="$school->name.($academicYear ? ' · '.$academicYear->name : '').' · '.now()->format('l, j F Y')"
    >
        <x-slot:actions>
            @can('reports.view')
                <x-ui.button :href="route('reports.index')" variant="secondary">Reports</x-ui.button>
            @endcan
            @can('announcements.manage')
                <x-ui.button :href="route('announcements.create')">New announcement</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- KPI row --}}
    @if ($metrics)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($metrics as $metric)
                <x-ui.stat
                    :label="$metric['label']"
                    :value="$metric['value']"
                    :note="$metric['note']"
                    data-aos="fade-up"
                    data-aos-delay="{{ $loop->index * 60 }}"
                />
            @endforeach
        </div>
    @endif

    {{-- Charts --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Enrollment trend" description="New student records over the last six months">
            <x-ui.bar-chart :series="$enrollmentTrend" aria-label="New student records by month" />
        </x-ui.card>

        @if ($feeBreakdown)
            <x-ui.card title="Fee collection" description="Against invoices raised this year">
                <div class="space-y-5">
                    @foreach ($feeBreakdown as $slice)
                        <x-ui.progress
                            :value="$slice['share']"
                            :label="$slice['label']"
                            :caption="App\Support\Money::format($slice['value'])"
                            :tone="$slice['label'] === 'Collected' ? 'success' : 'warning'"
                        />
                    @endforeach
                </div>

                <div class="mt-6 rounded-lg bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Collection rate</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular-nums text-slate-900">
                        {{ $feeBreakdown[0]['share'] }}%
                    </p>
                </div>
            </x-ui.card>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @if ($attendanceTrend)
            <x-ui.card class="lg:col-span-2" title="Attendance" description="Daily attendance rate over the last ten school days">
                <x-ui.bar-chart :series="$attendanceTrend" suffix="%" aria-label="Daily attendance rate" />
            </x-ui.card>
        @endif

        @if ($admissionFunnel)
            <x-ui.card title="Admissions" description="Applications by stage" :padded="false">
                <div class="divide-y divide-slate-100">
                    @foreach ($admissionFunnel as $stage)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="text-sm text-slate-700">{{ $stage['label'] }}</span>
                            <span class="font-display text-sm font-bold tabular-nums text-slate-900">{{ $stage['value'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-5 py-3">
                    <x-ui.button :href="route('admissions.index')" variant="ghost" size="sm">Open admissions</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    </div>

    {{-- Announcements, events, activity --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Latest announcements" :padded="false">
            <x-slot:actions>
                @can('announcements.manage')
                    <x-ui.button :href="route('announcements.index')" variant="ghost" size="sm">Manage</x-ui.button>
                @endcan
            </x-slot:actions>

            @forelse ($announcements as $announcement)
                <div class="border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-medium text-slate-900">{{ $announcement->title }}</p>
                        @if ($announcement->is_emergency)
                            <x-ui.badge tone="danger">Urgent</x-ui.badge>
                        @endif
                    </div>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $announcement->body }}</p>
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        {{ $announcement->published_at?->diffForHumans() }}
                    </p>
                </div>
            @empty
                <x-ui.empty-state icon="✦" title="No announcements" description="Published notices will appear here." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Upcoming events" :padded="false">
            <x-slot:actions>
                @can('events.manage')
                    <x-ui.button :href="route('events.index')" variant="ghost" size="sm">Manage</x-ui.button>
                @endcan
            </x-slot:actions>

            @forelse ($upcomingEvents as $event)
                <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="grid size-11 shrink-0 place-items-center rounded-lg bg-brand/8 text-center">
                        <span class="block font-display text-sm font-bold leading-none text-brand">{{ $event->starts_at->format('j') }}</span>
                        <span class="block text-[10px] uppercase text-brand/70">{{ $event->starts_at->format('M') }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $event->title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $event->starts_at->format('H:i') }}{{ $event->location ? ' · '.$event->location : '' }}
                        </p>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="◷" title="Nothing scheduled" description="Upcoming school events will appear here." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Quick actions">
            <div class="grid gap-2">
                @can('admissions.view')
                    <x-ui.button :href="route('admissions.index')" variant="secondary" class="justify-start">Review applications</x-ui.button>
                @endcan
                @can('attendance.record')
                    <x-ui.button :href="route('attendance.index')" variant="secondary" class="justify-start">Record attendance</x-ui.button>
                @endcan
                @can('students.create')
                    <x-ui.button :href="route('students.create')" variant="secondary" class="justify-start">Add a student</x-ui.button>
                @endcan
                @can('payments.record')
                    <x-ui.button :href="route('payments.create')" variant="secondary" class="justify-start">Record a payment</x-ui.button>
                @endcan
                @can('users.create')
                    <x-ui.button :href="route('users.create')" variant="secondary" class="justify-start">Create an account</x-ui.button>
                @endcan
            </div>
        </x-ui.card>
    </div>

    @can('audit.view')
        <x-ui.card class="mt-6" title="Recent activity" description="The latest recorded actions in this workspace" :padded="false">
            <x-slot:actions>
                <x-ui.button :href="route('audit.index')" variant="ghost" size="sm">View all</x-ui.button>
            </x-slot:actions>

            @forelse ($recentActivity as $entry)
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-slate-800">{{ $entry->description }}</p>
                        <p class="text-xs text-slate-500">{{ $entry->user_name ?? 'System' }} · {{ $entry->module }}</p>
                    </div>
                    <time datetime="{{ $entry->created_at?->toIso8601String() }}" class="text-xs text-slate-400">
                        {{ $entry->created_at?->diffForHumans() }}
                    </time>
                </div>
            @empty
                <x-ui.empty-state icon="☰" title="No activity yet" description="Actions will appear here as people use the system." />
            @endforelse
        </x-ui.card>
    @endcan
</x-layouts.app>
