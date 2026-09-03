<x-layouts.app title="Timetable" heading="Timetable">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Timetable' => null]" />

    <x-ui.page-header
        title="Timetable"
        description="View the week by class or by teacher. Clashes are refused when a lesson is saved."
    />

    {{-- View switcher --}}
    <x-ui.card class="mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <x-ui.field label="View by" name="view">
                    <x-ui.select name="view" :selected="$mode" :options="['class' => 'Class', 'teacher' => 'Teacher']" />
                </x-ui.field>
            </div>

            @if ($mode === 'class')
                <div class="min-w-52 flex-1">
                    <x-ui.field label="Class" name="section">
                        <x-ui.select name="section" :selected="$section?->id"
                                     :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                    </x-ui.field>
                </div>
            @else
                <div class="min-w-52 flex-1">
                    <x-ui.field label="Teacher" name="teacher">
                        <x-ui.select name="teacher" :selected="$teacher?->id"
                                     :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all()" />
                    </x-ui.field>
                </div>
            @endif

            <x-ui.button type="submit" variant="secondary">Show timetable</x-ui.button>
            <x-ui.button onclick="window.print()" variant="ghost" class="print:hidden">Print</x-ui.button>
        </form>
    </x-ui.card>

    @if (! $section && ! $teacher)
        <x-ui.card>
            <x-ui.empty-state icon="◷" title="Nothing to show"
                              description="Create classes and teachers before building a timetable." />
        </x-ui.card>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($days as $number => $dayName)
                @php $lessons = $entriesByDay[$number] ?? collect(); @endphp

                {{-- Weekends only appear once something is actually scheduled. --}}
                @continue ($number > 5 && $lessons->isEmpty())

                <x-ui.card :title="$dayName" :padded="false"
                           data-aos="fade-up" data-aos-delay="{{ ($number - 1) * 50 }}">
                    @forelse ($lessons as $entry)
                        <div class="group flex items-start gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                            <span class="shrink-0 pt-0.5 font-mono text-xs text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($entry->starts_at)->format('H:i') }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $entry->subject?->name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    @if ($mode === 'class')
                                        {{ $entry->teacher?->full_name ?? 'No teacher assigned' }}
                                    @else
                                        {{ $entry->section?->full_name }}
                                    @endif
                                    @if ($entry->room) · {{ $entry->room }} @endif
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    until {{ \Illuminate\Support\Carbon::parse($entry->ends_at)->format('H:i') }}
                                </p>
                            </div>

                            @if ($canManage)
                                <form method="POST" action="{{ route('timetable.destroy', $entry) }}"
                                      class="shrink-0 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100 print:hidden">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="grid size-7 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                            aria-label="Remove {{ $entry->subject?->name }} lesson">&times;</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-xs text-slate-400">No lessons</p>
                    @endforelse
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <x-ui.card class="mt-6 print:hidden" title="Add a lesson"
                   description="A lesson is refused if it would double-book the class, the teacher or the room.">
            <form method="POST" action="{{ route('timetable.store') }}" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @csrf

                <x-ui.field label="Class" name="section_id" required>
                    <x-ui.select name="section_id" required placeholder="Select a class"
                                 :selected="$section?->id"
                                 :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                </x-ui.field>

                <x-ui.field label="Subject" name="subject_id" required>
                    <x-ui.select name="subject_id" required placeholder="Select a subject"
                                 :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Teacher" name="teacher_id">
                    <x-ui.select name="teacher_id" placeholder="Not assigned"
                                 :selected="$teacher?->id"
                                 :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all()" />
                </x-ui.field>

                <x-ui.field label="Day" name="day_of_week" required>
                    <x-ui.select name="day_of_week" required :options="$days" />
                </x-ui.field>

                <x-ui.field label="Starts at" name="starts_at" required>
                    <x-ui.input name="starts_at" type="time" required />
                </x-ui.field>

                <x-ui.field label="Ends at" name="ends_at" required>
                    <x-ui.input name="ends_at" type="time" required />
                </x-ui.field>

                <x-ui.field label="Room" name="room">
                    <x-ui.input name="room" placeholder="Room 7" />
                </x-ui.field>

                <div class="flex items-end">
                    <x-ui.button type="submit" class="w-full">Add lesson</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif
</x-layouts.app>
