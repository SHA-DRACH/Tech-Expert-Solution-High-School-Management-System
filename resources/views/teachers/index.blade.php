<x-layouts.app title="Teachers & staff" heading="Teachers & staff">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Teachers & staff' => null]" />

    <x-ui.page-header title="Teachers & staff" description="Everyone on the teaching and support staff.">
        <x-slot:actions>
            <x-ui.button :href="route('exports.teachers', request()->query())" variant="secondary">Export CSV</x-ui.button>

            @can('teachers.create')
                <x-ui.button :href="route('imports.create', 'teachers')" variant="secondary">Import CSV</x-ui.button>
                <x-ui.button :href="route('teachers.create')">Add teacher</x-ui.button>
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
                label="Search teachers"
                :value="$filters['search']"
                placeholder="Name, staff number, email or phone…"
            />

            <div class="w-44">
                <label for="department" class="sr-only">Filter by department</label>
                <x-ui.select name="department" :selected="$filters['department']" placeholder="All departments"
                             :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
            </div>

            <div class="w-40">
                <label for="status" class="sr-only">Filter by status</label>
                <x-ui.select name="status" :selected="$filters['status']" placeholder="All statuses"
                             :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('teachers.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($teachers->isEmpty())
            <x-ui.empty-state icon="❋" title="No teachers yet"
                              description="Add your teaching staff so classes and subjects can be assigned.">
                @can('teachers.create')
                    <x-ui.button :href="route('teachers.create')">Add the first teacher</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.table :headings="['Staff number', 'Name', 'Department', 'Classes', 'Status', '']">
                @foreach ($teachers as $teacher)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{{ $teacher->staff_number }}</td>
                        <td class="px-5 py-3">
                            <a href="{{ route('teachers.show', $teacher) }}" class="font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $teacher->full_name }}
                            </a>
                            @if ($teacher->email)
                                <span class="block text-xs text-slate-500">{{ $teacher->email }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $teacher->department?->name ?? '—' }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $teacher->teaching_assignments_count }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$teacher->status" /></td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('teachers.show', $teacher)" variant="ghost" size="sm">View</x-ui.button>

                            @can('teachers.update')
                                <x-ui.button :href="route('teachers.edit', $teacher)" variant="ghost" size="sm">Edit</x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $teachers->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
