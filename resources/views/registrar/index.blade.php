<x-layouts.app title="Registrar" heading="Registrar">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Registrar' => null]" />

    <x-ui.page-header title="Registrar"
                      :description="$year ? 'Student records for '.$year->name.'.' : 'Student records.'">
        <x-slot:actions>
            @can('students.create')
                <x-ui.button :href="route('students.create')">Add a student</x-ui.button>
            @endcan
            <x-ui.button :href="route('students.index')" variant="secondary">All students</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Students on roll" :value="(string) $totalStudents" note="Active records" />

        {{-- Both of these are work waiting to be done, not statistics. --}}
        <x-ui.stat label="Not placed in a class" :value="(string) $unplaced"
                   :note="$year ? 'For '.$year->name : 'No academic year is current'" />
        <x-ui.stat label="No guardian on file" :value="(string) $withoutGuardian" note="Nobody to telephone" />
    </div>

    {{-- The counter work, each behind the permission of the module it opens. --}}
    <x-ui.card class="mt-6" title="At the counter"
               description="Find a student first — every one of these opens from their record.">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Student records', 'Search, open and print a student record.', 'students.index', 'students.view'],
                ['Documents', 'Birth certificates, transcripts and reports on file.', 'documents.index', 'students.view'],
                ['Grades', 'Approved results, per student and per class.', 'gradebook.index', 'reportcards.view'],
                ['Parents & guardians', 'Contacts, and who is linked to which child.', 'guardians.index', 'guardians.view'],
                ['Admissions', 'Applications, decisions and admission letters.', 'admissions.index', 'admissions.view'],
                ['Scholarships', 'Who holds an award against their fees.', 'scholarships.index', 'scholarships.view'],
            ] as [$label, $description, $route, $permission])
                @can($permission)
                    <a href="{{ route($route) }}"
                       class="group rounded-lg border border-slate-200 bg-white p-4 transition hover:border-brand hover:shadow-sm">
                        <p class="text-sm font-semibold text-slate-900 group-hover:text-brand">{{ $label }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $description }}</p>
                    </a>
                @endcan
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card class="mt-6" :padded="false" title="Classes"
               description="How many students are on the roll in each class.">
        @if ($classes->isEmpty())
            <x-ui.empty-state icon="▦" title="No classes"
                              description="Classes appear here once the academic structure is set up." />
        @else
            <x-ui.table :headings="['Class', 'Sections', 'Students', '']">
                @foreach ($classes as $class)
                    <tr>
                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-900">{{ $class->name }}</span>
                            @if ($class->level)
                                <span class="block text-xs text-slate-500">Level {{ $class->level }}</span>
                            @endif
                        </td>

                        <td class="px-5 py-3 text-sm text-slate-600">
                            {{ $class->sections->pluck('name')->implode(', ') ?: '—' }}
                        </td>

                        <td class="px-5 py-3 font-medium tabular-nums text-slate-900">
                            {{ $class->students_count }}
                        </td>

                        <td class="px-5 py-3 text-right">
                            {{-- Straight into the student list filtered to this
                                 class, which is what "how many?" is always
                                 followed by. --}}
                            <x-ui.button :href="route('students.index', ['class' => $class->id])"
                                         variant="ghost" size="sm">View students</x-ui.button>
                        </td>
                    </tr>
                @endforeach

                <tr class="border-t-2 border-slate-300 bg-slate-50">
                    <th scope="row" colspan="2" class="px-5 py-3 text-left text-sm font-semibold text-slate-900">Total</th>
                    <td class="px-5 py-3 font-semibold tabular-nums text-slate-900">
                        {{ $classes->sum('students_count') }}
                    </td>
                    <td></td>
                </tr>
            </x-ui.table>
        @endif
    </x-ui.card>
</x-layouts.app>
