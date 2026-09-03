<x-layouts.app title="Subjects" heading="Subjects">
    <x-ui.breadcrumbs :trail="['Overview' => route('portal'), 'Subjects' => null]" />

    <x-ui.page-header
        title="Subject management"
        description="Every subject the school teaches, which grades take it, and who teaches it."
    />

    @error('subject')
        <div class="enter-rise mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-5">
            @forelse ($subjects as $subject)
                @php $taught = $assignments->get($subject->id, collect()); @endphp

                <x-ui.card x-data="{ editing: false, assigning: false }" :padded="false">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <h2 class="flex flex-wrap items-center gap-2 font-display text-base font-bold text-slate-900">
                                {{ $subject->name }}
                                <span class="font-mono text-xs font-normal text-slate-400">{{ $subject->code }}</span>
                                @if ($subject->is_core)
                                    <x-ui.badge tone="info">Core</x-ui.badge>
                                @endif
                            </h2>

                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ $subject->department?->name ?? 'No department' }}
                                · {{ $subject->schoolClasses->count() }} {{ Str::plural('grade', $subject->schoolClasses->count()) }}
                                · {{ $taught->pluck('teacher_id')->unique()->count() }}
                                {{ Str::plural('teacher', $taught->pluck('teacher_id')->unique()->count()) }}
                            </p>
                        </div>

                        @can('academics.manage')
                            <div class="flex shrink-0 items-center gap-1">
                                <x-ui.button size="sm" variant="ghost" x-on:click="assigning = ! assigning; editing = false">Assign teacher</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" x-on:click="editing = ! editing; assigning = false">Edit</x-ui.button>
                            </div>
                        @endcan
                    </div>

                    {{-- Grades that take it --}}
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Grades</p>

                        @if ($subject->schoolClasses->isEmpty())
                            {{-- The failure this screen exists to prevent: a subject
                                 attached to no grade never reaches a mark sheet. --}}
                            <p class="mt-1.5 text-sm text-amber-700">
                                Not attached to any grade, so it will not appear on a mark sheet or a report card.
                                @can('academics.manage') Use <strong>Edit</strong> to attach it. @endcan
                            </p>
                        @else
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach ($subject->schoolClasses->sortBy('level') as $class)
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ $class->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Who teaches it --}}
                    <div class="px-5 py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned teachers</p>

                        @if ($taught->isEmpty())
                            <p class="mt-1.5 text-sm text-slate-500">
                                Nobody is assigned to teach this yet, so no marks can be recorded for it.
                            </p>
                        @else
                            <ul class="mt-2 space-y-1.5 text-sm">
                                @foreach ($taught->groupBy('teacher_id') as $rows)
                                    <li class="flex flex-wrap items-baseline gap-x-2">
                                        <span class="font-medium text-slate-800">{{ $rows->first()->teacher?->full_name }}</span>
                                        <span class="text-xs text-slate-500">
                                            {{ $rows->pluck('section')->filter()->map->full_name->sort()->join(', ') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @can('academics.manage')
                        {{-- Editing the subject --}}
                        <div x-show="editing" x-cloak x-collapse class="border-t border-slate-100 bg-slate-50 px-5 py-4">
                            <form method="POST" action="{{ route('subjects.update', $subject) }}" class="space-y-4">
                                @csrf
                                @method('PUT')

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.field label="Subject name" name="name" :id="'s-name-'.$subject->id">
                                        <x-ui.input name="name" :id="'s-name-'.$subject->id" :value="$subject->name" :remember="false" />
                                    </x-ui.field>

                                    <x-ui.field label="Subject code" name="code" :id="'s-code-'.$subject->id">
                                        <x-ui.input name="code" :id="'s-code-'.$subject->id" :value="$subject->code" :remember="false" />
                                    </x-ui.field>

                                    <x-ui.field label="Department" name="department_id" :id="'s-dept-'.$subject->id" class="sm:col-span-2">
                                        <x-ui.select name="department_id" :id="'s-dept-'.$subject->id"
                                                     placeholder="No department" :selected="$subject->department_id"
                                                     :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
                                    </x-ui.field>
                                </div>

                                <x-ui.field label="Grades that take this subject" name="class_ids" :id="'s-grades-'.$subject->id"
                                            hint="Unticking a grade stops it taking the subject. Marks already recorded are not affected.">
                                    <div class="grid max-h-44 gap-0.5 overflow-y-auto rounded-lg border border-slate-200 p-2 sm:grid-cols-2">
                                        @php $attached = $subject->schoolClasses->pluck('id'); @endphp

                                        @forelse ($classes as $class)
                                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-white">
                                                <input type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                                       @checked($attached->contains($class->id))>
                                                <span class="text-slate-700">{{ $class->name }}</span>
                                            </label>
                                        @empty
                                            <p class="px-2 py-1.5 text-sm text-slate-500">No classes set up yet.</p>
                                        @endforelse
                                    </div>
                                </x-ui.field>

                                <label class="flex items-center gap-2.5">
                                    <input type="checkbox" name="is_core" value="1"
                                           class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                           @checked($subject->is_core)>
                                    <span class="text-sm text-slate-700">Core subject</span>
                                </label>

                                <div class="flex flex-wrap items-center gap-2 border-t border-slate-200 pt-4">
                                    <x-ui.button type="submit" size="sm">Save subject</x-ui.button>

                                    <x-ui.confirm
                                        :action="route('subjects.destroy', $subject)"
                                        method="DELETE"
                                        title="Archive this subject?"
                                        :message="$subject->name.' will be removed from the timetable and from teaching assignments. This is refused while any assessment still refers to it.'"
                                        confirm="Archive subject"
                                    >Archive</x-ui.confirm>
                                </div>
                            </form>
                        </div>

                        {{-- Assigning a teacher --}}
                        <div x-show="assigning" x-cloak x-collapse class="border-t border-slate-100 bg-slate-50 px-5 py-4">
                            <form method="POST" action="{{ route('subjects.teachers.store', $subject) }}" class="space-y-4">
                                @csrf

                                <x-ui.field label="Teacher" name="teacher_id" :id="'s-teacher-'.$subject->id">
                                    <x-ui.select name="teacher_id" :id="'s-teacher-'.$subject->id"
                                                 placeholder="Select a teacher"
                                                 :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all()" />
                                </x-ui.field>

                                <x-ui.field label="Classes" name="section_id" :id="'s-sections-'.$subject->id"
                                            hint="A teacher can take the same subject in several classes.">
                                    <div class="grid max-h-44 gap-0.5 overflow-y-auto rounded-lg border border-slate-200 p-2 sm:grid-cols-2">
                                        @forelse ($sections as $section)
                                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-white">
                                                <input type="checkbox" name="section_id[]" value="{{ $section->id }}"
                                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                                <span class="text-slate-700">{{ $section->full_name }}</span>
                                            </label>
                                        @empty
                                            <p class="px-2 py-1.5 text-sm text-slate-500">No classes set up yet.</p>
                                        @endforelse
                                    </div>
                                </x-ui.field>

                                <x-ui.button type="submit" size="sm">Assign</x-ui.button>
                            </form>
                        </div>
                    @endcan
                </x-ui.card>
            @empty
                <x-ui.card>
                    <x-ui.empty-state
                        icon="◈"
                        title="No subjects yet"
                        description="Add the subjects your school teaches — Mathematics, English, Biology, and so on."
                    />
                </x-ui.card>
            @endforelse
        </div>

        @can('academics.manage')
            <div class="xl:sticky xl:top-6 xl:self-start">
                <x-ui.card title="New subject"
                           description="Name, code, department and the grades that take it.">
                    <form method="POST" action="{{ route('subjects.store') }}" class="space-y-4">
                        @csrf

                        <x-ui.field label="Subject name" name="name" required>
                            <x-ui.input name="name" placeholder="Mathematics" required />
                        </x-ui.field>

                        <x-ui.field label="Subject code" name="code" required hint="Short and unique, such as MTH101.">
                            <x-ui.input name="code" placeholder="MTH101" required />
                        </x-ui.field>

                        <x-ui.field label="Department" name="department_id">
                            <x-ui.select name="department_id" placeholder="No department"
                                         :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
                        </x-ui.field>

                        <x-ui.field label="Grades that take it" name="class_ids"
                                    hint="A subject attached to no grade never reaches a mark sheet.">
                            <div class="grid max-h-40 gap-0.5 overflow-y-auto rounded-lg border border-slate-200 p-2">
                                @forelse ($classes as $class)
                                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-50">
                                        <input type="checkbox" name="class_ids[]" value="{{ $class->id }}"
                                               class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                        <span class="text-slate-700">{{ $class->name }}</span>
                                    </label>
                                @empty
                                    <p class="px-2 py-1.5 text-sm text-slate-500">
                                        No classes yet — set them up in
                                        <a href="{{ route('academics.index') }}" class="font-medium text-brand hover:underline">Academic structure</a>.
                                    </p>
                                @endforelse
                            </div>
                        </x-ui.field>

                        <label class="flex items-start gap-2.5">
                            <input type="checkbox" name="is_core" value="1"
                                   class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand">
                            <span class="text-sm text-slate-700">
                                Core subject
                                <span class="block text-xs text-slate-500">Taken by every student in the grade.</span>
                            </span>
                        </label>

                        <x-ui.button type="submit" class="w-full">Create subject</x-ui.button>

                        <p class="text-xs text-slate-500">
                            Teachers are assigned afterwards, from the subject itself or from
                            <a href="{{ route('assignments.index') }}" class="font-medium text-brand hover:underline">Teaching assignments</a>.
                        </p>
                    </form>
                </x-ui.card>
            </div>
        @endcan
    </div>
</x-layouts.app>
