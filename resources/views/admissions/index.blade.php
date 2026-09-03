<x-layouts.app title="Admissions" heading="Admissions">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Admissions' => null]" />

    <x-ui.page-header
        title="Admission applications"
        description="Applications submitted through the public website, newest first."
    />

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-56 flex-1">
                <label for="search" class="sr-only">Search applications</label>
                <x-ui.input name="search" :value="$filters['search']" placeholder="Search by application number, applicant or guardian…" />
            </div>

            <div class="w-48">
                <label for="status" class="sr-only">Filter by status</label>
                <x-ui.select
                    name="status"
                    :selected="$filters['status']"
                    placeholder="All statuses"
                    :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()"
                />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('admissions.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($admissions->isEmpty())
            <x-ui.empty-state
                icon="✦"
                title="{{ array_filter($filters) ? 'No applications match those filters' : 'No applications yet' }}"
                description="{{ array_filter($filters) ? 'Try a different search term or status.' : 'Applications submitted through the public admissions form will appear here.' }}"
            />
        @else
            <x-ui.table :headings="['Application', 'Applicant', 'Intended class', 'Guardian', 'Documents', 'Status', '']">
                @foreach ($admissions as $admission)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3">
                            <a href="{{ route('admissions.show', $admission) }}" class="font-mono text-xs font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $admission->application_number }}
                            </a>
                            <span class="block text-xs text-slate-500">{{ $admission->created_at->format('j M Y') }}</span>
                        </td>
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $admission->student_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $admission->intended_class ?: '—' }}</td>
                        <td class="px-5 py-3 text-slate-600">
                            {{ $admission->guardian_name }}
                            <span class="block text-xs text-slate-500">{{ $admission->guardian_phone }}</span>
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $admission->documents_count }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$admission->status" /></td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('admissions.show', $admission)" variant="ghost" size="sm">Review</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $admissions->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
