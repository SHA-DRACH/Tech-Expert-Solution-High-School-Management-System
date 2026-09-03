<x-layouts.app title="My subjects">
    <x-ui.page-header
        title="My subjects"
        :description="'What you teach'.($term ? ' this term ('.$term->name.')' : '').', and the classes you teach it to.'"
    />

    @forelse ($subjects as $row)
        <x-ui.card class="mb-5" :padded="false">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div class="min-w-0">
                    <h2 class="font-display text-base font-bold text-slate-900">{{ $row['subject']->name }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ $row['subject']->code }}
                        · {{ $row['sections']->count() }} {{ Str::plural('class', $row['sections']->count()) }}
                        · {{ $row['students'] }} {{ Str::plural('student', $row['students']) }}
                    </p>
                </div>

                @if ($row['awaiting'] > 0)
                    {{-- Only the teacher can move these on, so it is worth
                         saying rather than leaving them to notice. --}}
                    <x-ui.badge tone="warning">
                        {{ $row['awaiting'] }} {{ Str::plural('assessment', $row['awaiting']) }} not submitted
                    </x-ui.badge>
                @endif
            </div>

            <ul class="divide-y divide-slate-50">
                @foreach ($row['sections'] as $section)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span class="text-sm font-medium text-slate-800">{{ $section->full_name }}</span>

                        <span class="flex flex-wrap items-center gap-1">
                            @can('grades.enter')
                                <x-ui.button
                                    :href="route('marks.index', ['section' => $section->id, 'subject' => $row['subject']->id])"
                                    variant="ghost"
                                    size="sm"
                                >Mark sheet</x-ui.button>
                            @endcan

                            @can('attendance.record')
                                <x-ui.button :href="route('attendance.index', ['section' => $section->id])"
                                             variant="ghost" size="sm">Attendance</x-ui.button>
                            @endcan

                            <x-ui.button :href="route('teaching.students', ['section' => $section->id])"
                                         variant="ghost" size="sm">Students</x-ui.button>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.empty-state
                icon="◈"
                title="You have not been assigned any subjects"
                description="A teacher can only record marks for a subject they are assigned to. Ask the academic office to set up your teaching for this year."
            />
        </x-ui.card>
    @endforelse
</x-layouts.app>
