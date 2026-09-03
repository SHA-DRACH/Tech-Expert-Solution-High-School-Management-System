<x-layouts.app title="Students" heading="Students">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Students' => null]" />

    <x-ui.page-header title="Students" description="Every student record in this school.">
        <x-slot:actions>
            @can('students.export')
                <x-ui.button :href="route('exports.students', request()->only('search', 'status'))" variant="secondary">
                    Export CSV
                </x-ui.button>
            @endcan

            @can('create', App\Models\Student::class)
                <x-ui.button :href="route('students.create')">Add student</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        {{-- Changing a dropdown filters straight away; the button stays for anyone
                 without JavaScript. Restricted to selects so it does not fire a second
                 time when the debounced search box loses focus. --}}
        <form method="GET"
              x-data
              x-on:change="$event.target.tagName === 'SELECT' && $el.requestSubmit()"
              class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <x-ui.live-search
                label="Search students"
                :value="$filters['search']"
                placeholder="Name, student number or parent name…"
            />

            <div class="w-44">
                <label for="status" class="sr-only">Filter by status</label>
                <x-ui.select
                    name="status"
                    :selected="$filters['status']"
                    placeholder="All statuses"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()"
                />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if ($filters['search'] || $filters['status'])
                <x-ui.button :href="route('students.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($students->isEmpty())
            <x-ui.empty-state
                icon="◉"
                title="{{ $filters['search'] || $filters['status'] ? 'No students match those filters' : 'No students yet' }}"
                description="{{ $filters['search'] || $filters['status'] ? 'Try a different search term or clear the filters.' : 'Students appear here once an admission is approved, or when you add one directly.' }}"
            >
                @can('create', App\Models\Student::class)
                    <x-ui.button :href="route('students.create')">Add the first student</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.table :headings="['Student number', 'Name', 'Parent / guardian', 'Status', '']">
                @foreach ($students as $student)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{{ $student->student_number }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('students.show', $student) }}" class="font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $student->full_name }}
                            </a>
                            @if ($student->date_of_birth)
                                <span class="block text-xs text-slate-500">Born {{ $student->date_of_birth->format('j M Y') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600">
                            {{ $student->guardians->map->full_name->join(', ') ?: '—' }}
                        </td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$student->status" /></td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('students.show', $student)" variant="ghost" size="sm">View</x-ui.button>

                            @can('update', $student)
                                <x-ui.button :href="route('students.edit', $student)" variant="ghost" size="sm">Edit</x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $students->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
