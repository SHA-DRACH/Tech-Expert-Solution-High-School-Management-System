<x-layouts.app title="Teacher portal">
    <x-ui.page-header
        :title="'Welcome, '.Str::before($teacher->first_name, ' ')"
        :description="($teacher->department?->name ?? 'Teaching staff').' · '.now()->format('l, j F Y')"
    />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat label="My classes" :value="(string) $sectionCount" note="Sections assigned to you" data-aos="fade-up" />
        <x-ui.stat label="My subjects" :value="(string) $subjectCount" note="Subjects you teach" data-aos="fade-up" data-aos-delay="60" />
        <x-ui.stat label="My students" :value="(string) $studentCount" note="Across your classes" data-aos="fade-up" data-aos-delay="120" />

        {{-- Null, not zero, when no register has ever been taken: a class whose
             attendance has not been recorded is not a class nobody attends. --}}
        <x-ui.stat
            label="Attendance"
            :value="$attendanceRate !== null ? $attendanceRate.'%' : '—'"
            :note="$attendanceRate !== null ? 'Your classes, last 30 days' : 'No register taken yet'"
            data-aos="fade-up"
            data-aos-delay="180"
        />

        <x-ui.stat label="Grades to finish" :value="(string) $pendingGrades" note="Draft or awaiting approval" data-aos="fade-up" data-aos-delay="240" />
    </div>

    @if ($sectionCount > 0 && $markedToday < $sectionCount)
        {{-- The one thing a teacher is expected to do every morning, and the
             easiest to forget. --}}
        <div class="enter-rise mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3.5">
            <p class="text-sm text-amber-900">
                Register not yet taken for
                {{ $sectionCount - $markedToday }} of your {{ $sectionCount }}
                {{ Str::plural('class', $sectionCount) }} today.
            </p>

            @can('attendance.record')
                <x-ui.button :href="route('attendance.index')" size="sm" variant="secondary">Take attendance</x-ui.button>
            @endcan
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Today's lessons" :description="now()->format('l, j F')" :padded="false">
            @forelse ($todayTimetable as $entry)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <span class="shrink-0 font-mono text-xs text-slate-500">
                        {{ \Illuminate\Support\Carbon::parse($entry->starts_at)->format('H:i') }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $entry->subject?->name }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $entry->section?->full_name }}{{ $entry->room ? ' · '.$entry->room : '' }}
                        </p>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="◷" title="No lessons today" />
            @endforelse
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" title="Recent assessments" description="Work you have set" :padded="false">
            @forelse ($recentAssessments as $assessment)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $assessment->title }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $assessment->subject?->name }} · {{ $assessment->section?->full_name }}
                            @if ($assessment->ends_at) · closes {{ $assessment->ends_at->format('j M, H:i') }} @endif
                        </p>
                    </div>
                    <x-ui.status-badge :status="$assessment->status" />
                </div>
            @empty
                <x-ui.empty-state icon="◈" title="Nothing set yet" description="Assessments you create will appear here." />
            @endforelse
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Announcements" :padded="false">
            @forelse ($announcements as $announcement)
                <div class="border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $announcement->title }}</p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $announcement->body }}</p>
                </div>
            @empty
                <x-ui.empty-state icon="✦" title="Nothing new" />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Quick links">
            <div class="grid gap-2">
                <x-ui.button :href="route('teaching.classes')" variant="secondary" class="justify-start">My classes</x-ui.button>
                <x-ui.button :href="route('teaching.students')" variant="secondary" class="justify-start">My students</x-ui.button>
                <x-ui.button :href="route('teaching.timetable')" variant="secondary" class="justify-start">My timetable</x-ui.button>
                @can('attendance.record')
                    <x-ui.button :href="route('attendance.index')" variant="secondary" class="justify-start">Record attendance</x-ui.button>
                @endcan
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
