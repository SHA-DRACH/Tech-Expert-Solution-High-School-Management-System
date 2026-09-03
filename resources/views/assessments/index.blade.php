<x-layouts.app title="Assessments" heading="Assessments">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Assessments' => null]" />

    <x-ui.page-header
        title="Assessments"
        description="Tests, assignments and examinations, with the marks behind them."
    >
        <x-slot:actions>
            @if ($canApprove)
                <x-ui.button :href="route('grades.approvals')" variant="secondary">Approval queue</x-ui.button>
            @endif
            @can('create', App\Models\Assessment::class)
                <x-ui.button :href="route('assessments.create')">New assessment</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="w-48">
                <label for="status" class="sr-only">Status</label>
                <x-ui.select name="status" :selected="$filters['status']" placeholder="All statuses"
                             :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
            </div>

            <div class="w-52">
                <label for="section" class="sr-only">Class</label>
                <x-ui.select name="section" :selected="$filters['section']" placeholder="All my classes"
                             :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('assessments.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($assessments->isEmpty())
            <x-ui.empty-state
                icon="◈"
                title="{{ array_filter($filters) ? 'Nothing matches those filters' : 'No assessments yet' }}"
                description="{{ array_filter($filters) ? 'Try a different status or class.' : 'Set a test or assignment, then enter the marks.' }}"
            >
                @can('create', App\Models\Assessment::class)
                    <x-ui.button :href="route('assessments.create')">Create the first assessment</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.table :headings="['Assessment', 'Class', 'Subject', 'Marks', 'Status', '']">
                @foreach ($assessments as $assessment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('assessments.scores', $assessment) }}"
                               class="font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $assessment->title }}
                            </a>
                            <span class="block text-xs text-slate-500">
                                {{ Str::headline($assessment->type) }}
                                @if ($assessment->ends_at) · closes {{ $assessment->ends_at->format('j M Y, H:i') }} @endif
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->section?->full_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $assessment->subject?->name }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $assessment->scores_count }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$assessment->status" /></td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('assessments.scores', $assessment)" variant="ghost" size="sm">
                                {{ $assessment->isEditable() ? 'Enter marks' : 'View marks' }}
                            </x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $assessments->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
