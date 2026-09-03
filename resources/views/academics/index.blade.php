<x-layouts.app title="Academics" heading="Academics">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Academics' => null]" />

    @error('structure')
        <div class="enter-rise mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror

    <x-ui.page-header
        title="Academic structure"
        description="Years, terms, classes, sections, subjects and departments. Everything here is yours to configure."
    />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Academic years" :value="(string) $years->count()" note="Configured" />
        <x-ui.stat label="Classes" :value="(string) $classes->count()" note="Grade levels" />
        <x-ui.stat label="Sections" :value="(string) $sections->count()" note="Class groups" />
        <x-ui.stat label="Subjects" :value="(string) $subjects->count()" note="Offered" />
    </div>

    {{-- Tabbed configuration panels --}}
    <div x-data="{ tab: 'classes' }" class="mt-6">
        <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
            @foreach (['classes' => 'Classes & sections', 'subjects' => 'Subjects', 'departments' => 'Departments', 'calendar' => 'Years & terms'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        class="relative px-4 py-2.5 text-sm font-medium transition-colors"
                        :class="tab === '{{ $key }}' ? 'text-brand' : 'text-slate-600 hover:text-brand'">
                    {{ $label }}
                    <span aria-hidden="true"
                          class="absolute inset-x-2 -bottom-px h-0.5 origin-center rounded-full bg-brand transition-transform duration-300"
                          :class="tab === '{{ $key }}' ? 'scale-x-100' : 'scale-x-0'"></span>
                </button>
            @endforeach
        </div>

        {{-- Classes & sections --}}
        <div x-show="tab === 'classes'" x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <div class="grid gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" title="Classes and their sections" :padded="false">
                    @forelse ($classes as $class)
                        <div x-data="{ editing: false }" class="border-b border-slate-100 px-5 py-4 last:border-0">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-display text-sm font-bold text-slate-900">{{ $class->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $class->stage ?? 'Grade level' }} ·
                                        {{ $class->enrollments_count }} {{ Str::plural('student', $class->enrollments_count) }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <x-ui.badge>{{ $class->sections_count }} {{ Str::plural('section', $class->sections_count) }}</x-ui.badge>

                                    @can('academics.manage')
                                        <x-ui.button size="sm" variant="ghost" x-on:click="editing = ! editing">Edit</x-ui.button>
                                    @endcan
                                </div>
                            </div>

                            @can('academics.manage')
                                <div x-show="editing" x-cloak x-collapse class="mt-3 rounded-lg bg-slate-50 p-4">
                                    <form method="POST" action="{{ route('academics.classes.update', $class) }}"
                                          class="grid gap-4 sm:grid-cols-3">
                                        @csrf
                                        @method('PUT')

                                        <x-ui.field label="Name" name="name" :id="'c-name-'.$class->id">
                                            <x-ui.input name="name" :id="'c-name-'.$class->id" :value="$class->name" :remember="false" />
                                        </x-ui.field>

                                        <x-ui.field label="Level" name="level" :id="'c-level-'.$class->id">
                                            <x-ui.input name="level" type="number" min="1" max="20"
                                                        :id="'c-level-'.$class->id" :value="$class->level" :remember="false" />
                                        </x-ui.field>

                                        <x-ui.field label="Stage" name="stage" :id="'c-stage-'.$class->id">
                                            <x-ui.input name="stage" :id="'c-stage-'.$class->id" :value="$class->stage"
                                                        placeholder="Junior High" :remember="false" />
                                        </x-ui.field>

                                        <div class="flex flex-wrap items-center gap-2 sm:col-span-3">
                                            <x-ui.button type="submit" size="sm">Save class</x-ui.button>

                                            <x-ui.confirm
                                                :action="route('academics.classes.destroy', $class)"
                                                method="DELETE"
                                                title="Archive this class?"
                                                :message="$class->name.' is archived, not deleted. This is refused while it still has sections or enrolments — last year\'s report cards name the class a child was in.'"
                                                confirm="Archive class"
                                            >Archive</x-ui.confirm>
                                        </div>
                                    </form>
                                </div>
                            @endcan

                            @php $classSections = $sections->where('school_class_id', $class->id); @endphp

                            @if ($classSections->isNotEmpty())
                                <div class="mt-3 space-y-1.5">
                                    @foreach ($classSections as $section)
                                        <div x-data="{ open: false }">
                                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5">
                                                <span class="text-xs text-slate-600">
                                                    {{ $section->name }}
                                                    <span class="text-slate-400">· {{ $section->enrollments_count }} {{ Str::plural('student', $section->enrollments_count) }}</span>
                                                    @if ($section->classTeacher)
                                                        <span class="text-slate-400">· {{ $section->classTeacher->full_name }}</span>
                                                    @endif
                                                </span>

                                                @can('academics.manage')
                                                    <x-ui.button size="sm" variant="ghost" x-on:click="open = ! open">Edit</x-ui.button>
                                                @endcan
                                            </div>

                                            @can('academics.manage')
                                                <div x-show="open" x-cloak x-collapse class="mt-1.5 rounded-lg bg-slate-50 p-4">
                                                    <form method="POST" action="{{ route('academics.sections.update', $section) }}"
                                                          class="grid gap-4 sm:grid-cols-2">
                                                        @csrf
                                                        @method('PUT')

                                                        <input type="hidden" name="school_class_id" value="{{ $section->school_class_id }}">

                                                        <x-ui.field label="Section name" name="name" :id="'sec-name-'.$section->id">
                                                            <x-ui.input name="name" :id="'sec-name-'.$section->id"
                                                                        :value="$section->name" :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Room" name="room" :id="'sec-room-'.$section->id">
                                                            <x-ui.input name="room" :id="'sec-room-'.$section->id"
                                                                        :value="$section->room" :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Capacity" name="capacity" :id="'sec-cap-'.$section->id">
                                                            <x-ui.input name="capacity" type="number" min="1" max="200"
                                                                        :id="'sec-cap-'.$section->id" :value="$section->capacity" :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Class teacher" name="class_teacher_id" :id="'sec-teacher-'.$section->id">
                                                            <x-ui.select name="class_teacher_id" :id="'sec-teacher-'.$section->id"
                                                                         placeholder="Not assigned" :selected="$section->class_teacher_id"
                                                                         :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all()" />
                                                        </x-ui.field>

                                                        <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                                                            <x-ui.button type="submit" size="sm">Save section</x-ui.button>

                                                            <x-ui.confirm
                                                                :action="route('academics.sections.destroy', $section)"
                                                                method="DELETE"
                                                                title="Archive this section?"
                                                                :message="$section->full_name.' is archived, not deleted. This is refused while students are still enrolled in it.'"
                                                                confirm="Archive section"
                                                            >Archive</x-ui.confirm>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty-state icon="◉" title="No classes yet" description="Create your grade levels to get started." />
                    @endforelse
                </x-ui.card>

                @can('academics.manage')
                    <div class="space-y-6">
                        <x-ui.card title="New class">
                            <form method="POST" action="{{ route('academics.classes.store') }}" class="space-y-4">
                                @csrf
                                <x-ui.field label="Name" name="name" required>
                                    <x-ui.input name="name" required placeholder="Grade 7" />
                                </x-ui.field>
                                <x-ui.field label="Level" name="level" hint="Used for ordering.">
                                    <x-ui.input name="level" type="number" min="1" max="20" />
                                </x-ui.field>
                                <x-ui.field label="Stage" name="stage">
                                    <x-ui.input name="stage" placeholder="Junior High" />
                                </x-ui.field>
                                <x-ui.button type="submit" class="w-full">Create class</x-ui.button>
                            </form>
                        </x-ui.card>

                        <x-ui.card title="New section">
                            <form method="POST" action="{{ route('academics.sections.store') }}" class="space-y-4">
                                @csrf
                                <x-ui.field label="Class" name="school_class_id" required>
                                    <x-ui.select name="school_class_id" placeholder="Select a class" required
                                                 :options="$classes->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" />
                                </x-ui.field>
                                <x-ui.field label="Section name" name="name" required>
                                    <x-ui.input name="name" required placeholder="10A" />
                                </x-ui.field>
                                <x-ui.field label="Class teacher" name="class_teacher_id">
                                    <x-ui.select name="class_teacher_id" placeholder="Not assigned"
                                                 :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all()" />
                                </x-ui.field>
                                <x-ui.field label="Room" name="room">
                                    <x-ui.input name="room" />
                                </x-ui.field>
                                <x-ui.button type="submit" class="w-full">Create section</x-ui.button>
                            </form>
                        </x-ui.card>
                    </div>
                @endcan
            </div>
        </div>

        {{-- Subjects --}}
        <div x-show="tab === 'subjects'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <div class="grid gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" title="Subjects" :padded="false">
                    @if ($subjects->isEmpty())
                        <x-ui.empty-state icon="◈" title="No subjects yet" description="Add the subjects your school teaches." />
                    @else
                        <x-ui.table :headings="['Code', 'Subject', 'Department', 'Core']">
                            @foreach ($subjects as $subject)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{{ $subject->code }}</td>
                                    <td class="px-5 py-3 font-medium text-slate-900">{{ $subject->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $subject->department?->name ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        @if ($subject->is_core)
                                            <x-ui.badge tone="success">Core</x-ui.badge>
                                        @else
                                            <x-ui.badge>Elective</x-ui.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </x-ui.card>

                @can('academics.manage')
                    <x-ui.card title="New subject">
                        <form method="POST" action="{{ route('academics.subjects.store') }}" class="space-y-4">
                            @csrf
                            <x-ui.field label="Name" name="name" required>
                                <x-ui.input name="name" required placeholder="Mathematics" />
                            </x-ui.field>
                            <x-ui.field label="Code" name="code" required>
                                <x-ui.input name="code" required placeholder="MTH101" />
                            </x-ui.field>
                            <x-ui.field label="Department" name="department_id">
                                <x-ui.select name="department_id" placeholder="Not assigned"
                                             :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
                            </x-ui.field>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_core" value="1" checked
                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                <span class="text-sm text-slate-600">Core subject</span>
                            </label>
                            <x-ui.button type="submit" class="w-full">Create subject</x-ui.button>
                        </form>
                    </x-ui.card>
                @endcan
            </div>
        </div>

        {{-- Departments --}}
        <div x-show="tab === 'departments'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <div class="grid gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" title="Departments" :padded="false">
                    @if ($departments->isEmpty())
                        <x-ui.empty-state icon="⌸" title="No departments yet" />
                    @else
                        <x-ui.table :headings="['Department', 'Code', 'Subjects', 'Teachers']">
                            @foreach ($departments as $department)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-slate-900">{{ $department->name }}</td>
                                    <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $department->code ?? '—' }}</td>
                                    <td class="px-5 py-3 tabular-nums text-slate-600">{{ $department->subjects_count }}</td>
                                    <td class="px-5 py-3 tabular-nums text-slate-600">{{ $department->teachers_count }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </x-ui.card>

                @can('academics.manage')
                    <x-ui.card title="New department">
                        <form method="POST" action="{{ route('academics.departments.store') }}" class="space-y-4">
                            @csrf
                            <x-ui.field label="Name" name="name" required>
                                <x-ui.input name="name" required placeholder="Sciences" />
                            </x-ui.field>
                            <x-ui.field label="Code" name="code">
                                <x-ui.input name="code" placeholder="SCI" />
                            </x-ui.field>
                            <x-ui.button type="submit" class="w-full">Create department</x-ui.button>
                        </form>
                    </x-ui.card>
                @endcan
            </div>
        </div>

        {{-- Years & terms --}}
        <div x-show="tab === 'calendar'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card title="Academic years" :padded="false">
                    @forelse ($years as $year)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $year->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $year->starts_on?->format('j M Y') }} – {{ $year->ends_on?->format('j M Y') }}
                                    · {{ $year->enrollments_count }} enrolled
                                </p>
                            </div>
                            @if ($year->is_current)
                                <x-ui.badge tone="success">Current</x-ui.badge>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty-state icon="◷" title="No academic years" />
                    @endforelse
                </x-ui.card>

                <x-ui.card title="Terms" :padded="false">
                    @forelse ($terms as $term)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $term->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $term->academicYear?->name }} ·
                                    {{ $term->starts_on?->format('j M') }} – {{ $term->ends_on?->format('j M Y') }}
                                </p>
                            </div>
                            @if ($term->is_current)
                                <x-ui.badge tone="success">Current</x-ui.badge>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty-state icon="◷" title="No terms" />
                    @endforelse
                </x-ui.card>
            </div>
        </div>
    </div>
</x-layouts.app>
