<x-layouts.app title="My students">
    <x-ui.page-header title="My students" description="Students in the classes you teach." />

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-56 flex-1">
                <label for="search" class="sr-only">Search students</label>
                <x-ui.input name="search" :value="$search" placeholder="Search by name or student number..." />
            </div>

            <div class="w-48">
                <label for="section" class="sr-only">Filter by class</label>
                <x-ui.select
                    name="section"
                    :selected="$sectionFilter"
                    placeholder="All my classes"
                    :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()"
                />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
        </form>

        @if ($students->isEmpty())
            <x-ui.empty-state icon="◉" title="No students found" description="Try a different search or class." />
        @else
            <x-ui.table :headings="['Student number', 'Name', 'Class', 'Status']">
                @foreach ($students as $student)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">{{ $student->student_number }}</td>
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $student->full_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $student->currentEnrollment?->section?->full_name ?? '—' }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$student->status" /></td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $students->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
