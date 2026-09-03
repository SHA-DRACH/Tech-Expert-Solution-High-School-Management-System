<x-layouts.app title="Student portal">
    <x-ui.page-header
        :title="'Welcome, '.Str::before($student->first_name, ' ')"
        :description="$student->student_number.($enrollment?->section ? ' · '.$enrollment->section->full_name : '')"
    />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Class" :value="$enrollment?->section?->full_name ?? 'Not assigned'" note="Current placement" />

        @if ($abilities['view_attendance'])
            <x-ui.stat label="Attendance" :value="$attendanceRate === null ? 'Not recorded' : $attendanceRate.'%'" note="Present or late" />
        @endif

        @if ($abilities['view_report_cards'])
            <x-ui.stat label="Report cards" :value="(string) $reportCardCount" note="Published" />
        @endif

        <x-ui.stat label="Today" :value="(string) $todayTimetable->count()" note="Lessons scheduled" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @if ($abilities['view_timetable'])
            <x-ui.card title="Today's lessons" :description="now()->format('l, j F')" :padded="false">
                @forelse ($todayTimetable as $entry)
                    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                        <span class="shrink-0 font-mono text-xs text-slate-500">
                            {{ \Illuminate\Support\Carbon::parse($entry->starts_at)->format('H:i') }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $entry->subject?->name }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $entry->teacher?->full_name }}{{ $entry->room ? ' · '.$entry->room : '' }}
                            </p>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="◷" title="No lessons today" description="Enjoy the break." />
                @endforelse
            </x-ui.card>
        @endif

        @if ($abilities['view_grades'])
            <x-ui.card class="lg:col-span-2" title="Recent grades" description="Approved marks only" :padded="false">
                <x-slot:actions>
                    <x-ui.button :href="route('student.grades')" variant="ghost" size="sm">View all</x-ui.button>
                </x-slot:actions>

                @forelse ($recentGrades as $grade)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $grade->subject }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $grade->title }}</p>
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
                    <x-ui.empty-state icon="◈" title="No grades yet" description="Marks appear once your teachers submit them." />
                @endforelse
            </x-ui.card>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Announcements" :padded="false">
            @forelse ($announcements as $announcement)
                <div class="border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $announcement->title }}</p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $announcement->body }}</p>
                </div>
            @empty
                <x-ui.empty-state icon="✦" title="Nothing new" description="School notices will appear here." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Upcoming events" :padded="false">
            @forelse ($events as $event)
                <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="grid size-11 shrink-0 place-items-center rounded-lg bg-brand/8 text-center">
                        <span class="block font-display text-sm font-bold leading-none text-brand">{{ $event->starts_at->format('j') }}</span>
                        <span class="block text-[10px] uppercase text-brand/70">{{ $event->starts_at->format('M') }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $event->title }}</p>
                        <p class="text-xs text-slate-500">{{ $event->starts_at->format('H:i') }}</p>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="◷" title="Nothing scheduled" />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
