@php
    use App\Support\Money;

@endphp

<x-layouts.app title="Parent portal">
    <x-ui.page-header
        :title="'Welcome, '.Str::before($guardian->full_name, ' ')"
        :description="now()->format('l, j F Y')"
    />

    <x-portal.child-selector :children="$children" :selected="$child" />

    @if ($child === null)
        <x-ui.card>
            <x-ui.empty-state
                icon="◉"
                title="No children linked yet"
                description="Once the school links your children to this account, their information appears here."
            />
        </x-ui.card>
    @else
        {{-- Summary tiles --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat
                label="Class"
                :value="$summary['class'] ?? 'Not assigned'"
                note="Current placement"
                data-aos="fade-up"
            />

            <x-ui.stat
                label="Attendance"
                :value="$summary['attendanceRate'] === null ? 'Not recorded' : $summary['attendanceRate'].'%'"
                note="Across the year so far"
                data-aos="fade-up" data-aos-delay="60"
            />

            <x-ui.stat
                label="Outstanding fees"
                :value="$summary['outstandingMinor'] === null ? 'Hidden' : Money::format($summary['outstandingMinor'])"
                :note="$summary['outstandingMinor'] === null ? 'Not shared with this account' : 'Balance still to pay'"
                data-aos="fade-up" data-aos-delay="120"
            />

            <x-ui.stat
                label="Report cards"
                :value="(string) $summary['reportCards']"
                note="Published and ready to read"
                data-aos="fade-up" data-aos-delay="180"
            />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- Recent grades --}}
            <x-ui.card class="lg:col-span-2" title="Recent grades" description="Only marks the school has approved" :padded="false">
                <x-slot:actions>
                    @if ($summary['canViewAcademics'])
                        <x-ui.button :href="route('parent.grades', ['child' => $child->id])" variant="ghost" size="sm">
                            View all
                        </x-ui.button>
                    @endif
                </x-slot:actions>

                @if (! $summary['canViewAcademics'])
                    <x-ui.empty-state
                        icon="◈"
                        title="Academic records are not shared with this account"
                        description="Ask the school office if you should have access to this child's grades."
                    />
                @else
                    @forelse ($recentGrades as $grade)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $grade->subject }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $grade->title }} · {{ Str::headline($grade->type) }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <span class="font-display text-sm font-bold tabular-nums text-slate-900">
                                    {{ rtrim(rtrim((string) $grade->score, '0'), '.') }}<span class="text-slate-400">/{{ $grade->max }}</span>
                                </span>
                                @if ($grade->grade)
                                    <x-ui.badge :tone="in_array($grade->grade, ['A', 'B']) ? 'success' : ($grade->grade === 'F' ? 'danger' : 'warning')">
                                        {{ $grade->grade }}
                                    </x-ui.badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state
                            icon="◈"
                            title="No grades published yet"
                            description="Marks appear here once teachers submit them and the school approves them."
                        />
                    @endforelse
                @endif
            </x-ui.card>

            {{-- Announcements --}}
            <x-ui.card title="Announcements" :padded="false">
                @forelse ($announcements as $announcement)
                    <div class="border-b border-slate-100 px-5 py-3.5 last:border-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-slate-900">{{ $announcement->title }}</p>
                            @if ($announcement->is_emergency)
                                <x-ui.badge tone="danger">Urgent</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 line-clamp-3 text-xs text-slate-500">{{ $announcement->body }}</p>
                        <p class="mt-1.5 text-[11px] text-slate-400">{{ $announcement->published_at?->diffForHumans() }}</p>
                    </div>
                @empty
                    <x-ui.empty-state icon="✦" title="Nothing new" description="School notices will appear here." />
                @endforelse
            </x-ui.card>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- Upcoming events --}}
            <x-ui.card class="lg:col-span-2" title="Upcoming events" :padded="false">
                @forelse ($events as $event)
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
                    <x-ui.empty-state icon="◷" title="Nothing scheduled" description="School events will appear here." />
                @endforelse
            </x-ui.card>

            {{-- Requests --}}
            <x-ui.card title="Contact the school" description="Ask a question or request a meeting">
                <p class="text-sm text-slate-600">
                    You have <strong class="font-semibold text-slate-900">{{ $openRequests }}</strong>
                    {{ Str::plural('request', $openRequests) }} awaiting a reply.
                </p>

                <div class="mt-4 grid gap-2">
                    <x-ui.button :href="route('parent.requests')" class="justify-start">Send a request</x-ui.button>
                    <x-ui.button :href="route('parent.teachers', ['child' => $child->id])" variant="secondary" class="justify-start">
                        See the teaching staff
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif
</x-layouts.app>
