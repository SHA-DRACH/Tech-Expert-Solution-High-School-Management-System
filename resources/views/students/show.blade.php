<x-layouts.app :title="$student->full_name" :heading="$student->full_name">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Students' => route('students.index'), $student->full_name => null]" />

    <x-ui.page-header :title="$student->full_name" :description="$student->student_number">
        <x-slot:actions>
            <x-ui.status-badge :status="$student->status" />

            @can('reportcards.view')
                <x-ui.button :href="route('gradebook.student', $student)" variant="secondary">Results</x-ui.button>
            @endcan

            @can('update', $student)
                <x-ui.button :href="route('students.edit', $student)">Edit record</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Personal information">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'Student number' => $student->student_number,
                    'Full name' => $student->full_name,
                    'Gender' => $student->gender,
                    'Date of birth' => $student->date_of_birth?->format('j F Y'),
                    'Nationality' => $student->nationality,
                    'Portal account' => $student->user?->email,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Parents & guardians" :padded="false">
            @forelse ($student->guardians as $guardian)
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $guardian->full_name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $guardian->pivot->relationship }}{{ $guardian->phone ? ' · '.$guardian->phone : '' }}
                        </p>
                    </div>
                    @if ($guardian->pivot->is_primary)
                        <x-ui.badge tone="info">Primary</x-ui.badge>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="❋" title="No guardian linked" description="Link a parent or guardian to this student." />
            @endforelse
        </x-ui.card>
    </div>

    {{-- Class placement and subjects --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Class placement"
                   :description="$current ? 'Currently in '.$current->section?->full_name.' for '.$current->academicYear?->name.'.' : 'This student is not in a class yet.'">
            @error('enrollment')
                <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $message }}</p>
            @enderror

            @if (! $current)
                {{-- Said plainly. A student with no placement cannot be marked,
                     registered, timetabled or reported on at all. --}}
                <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Until this student is placed in a class they will not appear on any register, mark sheet or
                    report card.
                </p>
            @endif

            @if ($canEnrol && $years->isNotEmpty() && $sections->isNotEmpty())
                <form method="POST" action="{{ route('students.enrol', $student) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf

                    <x-ui.field label="Academic year" name="academic_year_id" required>
                        <x-ui.select name="academic_year_id" :selected="$year?->id"
                                     :options="$years->mapWithKeys(fn ($y) => [$y->id => $y->name])->all()" />
                    </x-ui.field>

                    <x-ui.field label="Class" name="section_id" required>
                        <x-ui.select name="section_id" placeholder="Select a class" :selected="$current?->section_id"
                                     :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                    </x-ui.field>

                    <x-ui.field label="Roll number" name="roll_number" hint="Optional, for the class register.">
                        <x-ui.input name="roll_number" :value="$current?->roll_number" :remember="false" />
                    </x-ui.field>

                    <div class="flex items-end">
                        <x-ui.button type="submit">{{ $current ? 'Move class' : 'Enrol student' }}</x-ui.button>
                    </div>
                </form>
            @elseif ($years->isEmpty() || $sections->isEmpty())
                <p class="text-sm text-slate-500">
                    Set up an academic year and at least one class in
                    <a href="{{ route('academics.index') }}" class="font-medium text-brand hover:underline">Academic structure</a>
                    before enrolling students.
                </p>
            @endif

            @if ($student->enrollments->isNotEmpty())
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Placement history</p>

                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($student->enrollments->sortByDesc(fn ($e) => $e->academicYear?->starts_on) as $enrollment)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-slate-800">
                                    {{ $enrollment->section?->full_name ?? 'No class' }}
                                    <span class="text-xs text-slate-500">· {{ $enrollment->academicYear?->name }}</span>
                                </span>

                                <span class="flex items-center gap-2">
                                    <x-ui.status-badge :status="$enrollment->status" />

                                    @if ($canEnrol)
                                        <x-ui.confirm
                                            :action="route('students.enrolment.destroy', $enrollment)"
                                            method="DELETE"
                                            title="Remove this placement?"
                                            :message="'This removes '.$student->full_name.'\'s placement in '.($enrollment->section?->full_name ?? 'that class').'. It is refused if marks were recorded against it — change the class instead.'"
                                            confirm="Remove placement"
                                        >Remove</x-ui.confirm>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Subjects taken"
                   :description="$year ? 'What this student takes in '.$year->name.'.' : 'Subjects for the current year.'">
            @if (! $current)
                <p class="text-sm text-slate-500">Place the student in a class first — the subjects on offer come from the class.</p>
            @elseif ($offered->isEmpty())
                <p class="text-sm text-amber-700">
                    {{ $current->section?->schoolClass?->name }} has no subjects attached.
                    <a href="{{ route('subjects.index') }}" class="font-medium underline underline-offset-2">Attach some</a>.
                </p>
            @else
                <form method="POST" action="{{ route('students.subjects.update', $student) }}">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="academic_year_id" value="{{ $year->id }}">

                    @error('subject_ids')
                        <p class="mb-3 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror

                    <div class="max-h-64 space-y-0.5 overflow-y-auto">
                        @foreach ($offered as $subject)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                                <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}"
                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                       @checked($taken->contains($subject->id))>
                                <span class="text-slate-700">
                                    {{ $subject->name }}
                                    @if ($subject->is_core)
                                        <span class="text-xs text-slate-400">· core</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <x-ui.button type="submit" size="sm" class="w-full">Save subjects</x-ui.button>
                    </div>

                    <p class="mt-3 text-xs text-slate-500">
                        Unticking a subject removes this student from its mark sheet — that is how an elective
                        taken by only part of the class is recorded.
                    </p>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
