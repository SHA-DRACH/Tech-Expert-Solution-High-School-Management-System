<x-layouts.app title="Teaching assignments" heading="Teaching assignments">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Teaching assignments' => null]" />

    <x-ui.page-header
        title="Teaching assignments"
        description="Who teaches which subject to which class. A teacher can only record marks for a subject they are assigned to."
    />

    @if ($year === null)
        <x-ui.card>
            <x-ui.empty-state
                icon="▤"
                title="No academic year yet"
                description="Teaching is assigned for a particular year, so set one up first."
            >
                @can('academics.manage')
                    <x-ui.button :href="route('settings.years.index')">Set up the calendar</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        @if ($unassigned->isNotEmpty())
            {{--
                Worth surfacing: an unassigned teacher signs in to an empty
                workspace and cannot enter a single mark, and the reason is
                never obvious from their side of the screen.
            --}}
            <div class="enter-rise mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <p class="text-sm font-semibold text-amber-900">
                    {{ $unassigned->count() }} {{ Str::plural('teacher', $unassigned->count()) }}
                    {{ $unassigned->count() === 1 ? 'has' : 'have' }} nothing to teach in {{ $year->name }}.
                </p>
                <p class="mt-1 text-sm text-amber-800">
                    {{ $unassigned->pluck('full_name')->join(', ') }} —
                    until they are assigned a class and subject they will sign in to an empty workspace and
                    cannot record any marks.
                </p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="min-w-0 space-y-5">
                @forelse ($grouped as $teacherId => $rows)
                    @php $teacher = $rows->first()->teacher; @endphp

                    <x-ui.card :padded="false">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-slate-900">{{ $teacher?->full_name ?? 'Unknown teacher' }}</h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $teacher?->staff_number }} ·
                                    {{ $rows->count() }} {{ Str::plural('assignment', $rows->count()) }} ·
                                    {{ $rows->pluck('subject_id')->unique()->count() }}
                                    {{ Str::plural('subject', $rows->pluck('subject_id')->unique()->count()) }}
                                </p>
                            </div>

                            @if ($teacher)
                                <x-ui.button :href="route('teachers.show', $teacher)" variant="ghost" size="sm">Staff record</x-ui.button>
                            @endif
                        </div>

                        <ul class="divide-y divide-slate-50">
                            @foreach ($rows as $assignment)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-2.5">
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-slate-800">{{ $assignment->subject?->name }}</span>
                                        <span class="ml-2 text-sm text-slate-500">{{ $assignment->section?->full_name }}</span>
                                    </div>

                                    @can('academics.manage')
                                        <x-ui.confirm
                                            :action="route('assignments.destroy', $assignment)"
                                            method="DELETE"
                                            title="Remove this assignment?"
                                            :message="$assignment->teacher?->full_name.' will no longer be able to record marks for '.$assignment->subject?->name.' in '.$assignment->section?->full_name.'. Marks already recorded are not affected.'"
                                            confirm="Remove assignment"
                                        >Remove</x-ui.confirm>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @empty
                    <x-ui.card>
                        <x-ui.empty-state
                            icon="⌘"
                            title="Nothing has been assigned yet"
                            description="Until a teacher is assigned a class and subject, they cannot record any marks."
                        />
                    </x-ui.card>
                @endforelse
            </div>

            @can('academics.manage')
                <div class="lg:sticky lg:top-6 lg:self-start">
                    <x-ui.card title="Assign teaching"
                               description="Tick several classes and several subjects to assign every combination at once.">
                        <form method="POST" action="{{ route('assignments.store') }}" class="space-y-5">
                            @csrf

                            <x-ui.field label="Teacher" name="teacher_id" required>
                                <x-ui.select
                                    name="teacher_id"
                                    placeholder="Select a teacher"
                                    :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all()"
                                />
                            </x-ui.field>

                            <x-ui.field label="Classes" name="section_id" required>
                                <div class="max-h-44 space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 p-2">
                                    @forelse ($sections as $section)
                                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-50">
                                            <input type="checkbox" name="section_id[]" value="{{ $section->id }}"
                                                   class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                            <span class="text-slate-700">{{ $section->full_name }}</span>
                                        </label>
                                    @empty
                                        <p class="px-2 py-1.5 text-sm text-slate-500">No classes set up yet.</p>
                                    @endforelse
                                </div>
                            </x-ui.field>

                            <x-ui.field label="Subjects" name="subject_id" required>
                                <div class="max-h-44 space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 p-2">
                                    @forelse ($subjects as $subject)
                                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-50">
                                            <input type="checkbox" name="subject_id[]" value="{{ $subject->id }}"
                                                   class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                            <span class="text-slate-700">{{ $subject->name }}</span>
                                        </label>
                                    @empty
                                        <p class="px-2 py-1.5 text-sm text-slate-500">No subjects set up yet.</p>
                                    @endforelse
                                </div>
                            </x-ui.field>

                            <x-ui.button type="submit" class="w-full">Assign</x-ui.button>

                            <p class="text-xs text-slate-500">
                                Assigning is additive and safe to repeat — a combination a teacher already has is
                                left alone rather than duplicated.
                            </p>
                        </form>
                    </x-ui.card>
                </div>
            @endcan
        </div>
    @endif
</x-layouts.app>
