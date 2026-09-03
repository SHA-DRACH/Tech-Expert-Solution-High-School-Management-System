<x-layouts.app title="Grade approvals" heading="Grade approvals">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Grade approvals' => null]" />

    <x-ui.page-header
        title="Grade approvals"
        :description="$pending->count().' '.Str::plural('set', $pending->count()).' of marks waiting for a decision'"
    />

    <x-ui.card title="Waiting for approval" :padded="false">
        @if ($pending->isEmpty())
            <x-ui.empty-state
                icon="✓"
                title="Nothing waiting"
                description="Marks submitted by teachers will appear here for review."
            />
        @else
            <x-ui.table :headings="['Assessment', 'Class', 'Subject', 'Teacher', 'Marks', 'Submitted', '']">
                @foreach ($pending as $assessment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-900">{{ $assessment->title }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->section?->full_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->subject?->name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->teacher?->full_name ?? '—' }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $assessment->scores_count }}</td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $assessment->submitted_at?->diffForHumans() }}</td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('grades.review', $assessment)" size="sm">Review</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    @if ($recentlyDecided->isNotEmpty())
        <x-ui.card class="mt-6" title="Recent decisions" :padded="false">
            <x-ui.table :headings="['Assessment', 'Class', 'Teacher', 'Decision', 'When']">
                @foreach ($recentlyDecided as $assessment)
                    <tr>
                        <td class="px-5 py-3 text-slate-700">{{ $assessment->title }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->section?->full_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->teacher?->full_name ?? '—' }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$assessment->status" /></td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $assessment->approved_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</x-layouts.app>
