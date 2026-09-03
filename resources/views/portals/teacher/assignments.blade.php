<x-layouts.app title="My assignments">
    <x-ui.page-header
        title="Assignments"
        description="Work you have set, and who has handed it in."
    >
        <x-slot:actions>
            @can('grades.enter')
                <x-ui.button :href="route('assessments.create')">Set an assignment</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="◈"
                title="You have not set any assignments"
                description="An assignment is an assessment of type “assignment”. Students see it in their portal and hand it in there."
            >
                @can('grades.enter')
                    <x-ui.button :href="route('assessments.create')">Set your first assignment</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padded="false">
            <x-ui.table :headings="['Assignment', 'Class', 'Closes', 'Handed in', '']">
                @foreach ($rows as $row)
                    @php
                        $assessment = $row['assessment'];
                        $outstanding = max(0, $row['expected'] - $row['handed']);
                    @endphp

                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <span class="block font-medium text-slate-900">{{ $assessment->title }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $assessment->subject?->name }} · out of {{ $assessment->max_score }}
                            </span>
                        </td>

                        <td class="px-5 py-3 text-slate-600">{{ $assessment->section?->full_name }}</td>

                        <td class="px-5 py-3">
                            @if ($assessment->ends_at)
                                <span @class([
                                    'text-sm',
                                    'text-slate-500' => ! $row['closed'],
                                    'text-slate-400' => $row['closed'],
                                ])>
                                    {{ $assessment->ends_at->format('j M Y') }}
                                    <span class="block text-xs">
                                        {{ $assessment->ends_at->format('H:i') }}
                                        @if ($row['closed']) · closed @endif
                                    </span>
                                </span>
                            @else
                                <span class="text-sm text-slate-400">No deadline</span>
                            @endif
                        </td>

                        <td class="px-5 py-3">
                            <span class="text-sm font-medium text-slate-900 tabular-nums">
                                {{ $row['handed'] }} of {{ $row['expected'] }}
                            </span>

                            <span class="mt-0.5 block text-xs">
                                @if ($outstanding > 0)
                                    {{-- The question a teacher actually asks. --}}
                                    <span class="text-amber-700">{{ $outstanding }} still owing</span>
                                @else
                                    <span class="text-emerald-700">All in</span>
                                @endif

                                @if ($row['late'] > 0)
                                    · <span class="text-rose-600">{{ $row['late'] }} late</span>
                                @endif
                            </span>
                        </td>

                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('assessments.scores', $assessment)" variant="ghost" size="sm">
                                Mark
                            </x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                “Late” is measured against the time the assignment closed, not the end of that day.
            </p>
        </x-ui.card>
    @endif
</x-layouts.app>
