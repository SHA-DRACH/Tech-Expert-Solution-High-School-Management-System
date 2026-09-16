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

    {{--
        Where period-end marking starts: every class and subject this teacher
        marks, and how far the current period has got. One click opens that
        class with every student listed, ready for marks.
    --}}
    @can('grades.enter')
        <x-ui.card class="mt-6" :padded="false"
                   title="Enter marks"
                   :description="$currentPeriod ? ucfirst($currentPeriod->label()).($currentPeriod->ends_on ? ' · ends '.$currentPeriod->ends_on->format('j M') : '') : 'Periods have not been set up for this year yet.'">
            <x-slot:actions>
                <x-ui.button :href="route('gradesheet.index')" variant="ghost" size="sm">Grade sheet</x-ui.button>
            </x-slot:actions>

            @if ($marking->isEmpty())
                <x-ui.empty-state icon="⌘" title="No classes assigned"
                                  description="Once the academic office assigns you a class and subject, it appears here." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <th scope="col" class="px-5 py-2.5">Class</th>
                                <th scope="col" class="px-5 py-2.5">Subject</th>
                                <th scope="col" class="px-5 py-2.5">Students</th>
                                <th scope="col" class="px-5 py-2.5">Marked this period</th>
                                <th scope="col" class="px-5 py-2.5">Status</th>
                                <th scope="col" class="px-5 py-2.5"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($marking as $row)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="px-5 py-3 font-medium text-slate-900">{{ $row['section']->full_name }}</td>
                                    <td class="px-5 py-3 text-slate-700">{{ $row['subject']->name }}</td>
                                    <td class="px-5 py-3 tabular-nums text-slate-700">{{ $row['students'] }}</td>
                                    <td class="px-5 py-3">
                                        <span class="tabular-nums text-slate-700">{{ $row['marked'] }} of {{ $row['students'] }}</span>
                                        @if ($row['students'] > 0)
                                            <span class="mt-1 block h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                                <span class="block h-full rounded-full bg-brand" style="width: {{ min(100, round($row['marked'] / $row['students'] * 100)) }}%"></span>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3"><x-ui.status-badge :status="$row['status']" /></td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($currentPeriod)
                                            <x-ui.button size="sm" :href="route('gradesheet.index', [
                                                'section' => $row['section']->id,
                                                'subject' => $row['subject']->id,
                                                'sheet' => 'period:'.$currentPeriod->id,
                                            ])">Enter marks</x-ui.button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endcan

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
