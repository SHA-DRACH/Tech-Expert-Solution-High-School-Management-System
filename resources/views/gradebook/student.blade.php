<x-layouts.app :title="$student->full_name.' · results'" heading="Student results">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Gradebook' => route('gradebook.index'),
        $student->full_name => null,
    ]" />

    <x-ui.page-header
        :title="$student->full_name"
        :description="$student->student_number.' · '.($student->currentEnrollment?->section?->full_name ?? 'not enrolled')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('students.show', $student)" variant="secondary">Student record</x-ui.button>
            <x-ui.button :href="route('gradebook.index')" variant="secondary">Back to gradebook</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Every term of the year, so progress is visible rather than a snapshot. --}}
    @if ($progress->isNotEmpty())
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            @foreach ($progress as $item)
                <a href="{{ route('gradebook.student', ['student' => $student, 'term' => $item['term']->id]) }}"
                   @class([
                       'rounded-xl border p-4 transition-colors',
                       'border-brand bg-brand/5' => $term && $item['term']->id === $term->id,
                       'border-slate-200 bg-white hover:border-slate-300' => ! $term || $item['term']->id !== $term->id,
                   ])>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['term']->name }}</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular-nums text-slate-900">
                        {{ $item['average'] !== null ? $item['average'].'%' : '—' }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $item['average'] === null
                            ? 'No approved results yet'
                            : ($item['grade'] ? 'Grade '.$item['grade'].' · ' : '').$item['assessments'].' '.Str::plural('mark', $item['assessments']) }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif

    @if ($term === null || $results === null)
        <x-ui.card>
            <x-ui.empty-state title="No term selected" description="Choose a term above." />
        </x-ui.card>
    @elseif ($results['subjects']->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="◇"
                title="No approved results for {{ $term->name }}"
                description="Marks appear here once the academic office has approved them. Marks a teacher has entered but not had approved are not shown, because they may still change."
            />
        </x-ui.card>
    @else
        <div class="space-y-5">
            @foreach ($results['subjects'] as $subject)
                <x-ui.card :padded="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-slate-900">{{ $subject['subject']->name }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $subject['teacher']?->full_name ?? 'No teacher recorded' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <span @class([
                                'font-display text-lg font-bold tabular-nums',
                                'text-rose-600' => $subject['passed'] === false,
                                'text-slate-900' => $subject['passed'] !== false,
                            ])>{{ $subject['average'] }}%</span>

                            @if ($subject['grade'])
                                <x-ui.badge :tone="$subject['passed'] === false ? 'danger' : 'success'">
                                    {{ $subject['grade'] }}
                                </x-ui.badge>
                            @endif
                        </div>
                    </div>

                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-left">
                                <th scope="col" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Assessment</th>
                                <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Mark</th>
                                <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Percentage</th>
                                <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Weight</th>
                                <th scope="col" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subject['assessments'] as $item)
                                <tr class="border-b border-slate-50 last:border-0">
                                    <td class="px-5 py-2">
                                        <span class="font-medium text-slate-800">{{ $item['title'] }}</span>
                                        <span class="ml-1.5 text-xs uppercase text-slate-400">{{ $item['type'] }}</span>
                                    </td>
                                    <td class="px-3 py-2 tabular-nums text-slate-700">
                                        {{ rtrim(rtrim(number_format($item['score'], 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($item['max'], 2), '0'), '.') }}
                                    </td>
                                    <td class="px-3 py-2 tabular-nums text-slate-700">{{ $item['percentage'] }}%</td>
                                    <td class="px-3 py-2 tabular-nums text-slate-500">&times;{{ rtrim(rtrim(number_format($item['weight'], 2), '0'), '.') }}</td>
                                    <td class="px-5 py-2 text-slate-600">{{ $item['remark'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.card>
            @endforeach

            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $term->name }} average</p>
                        <p class="mt-1 font-display text-3xl font-bold tabular-nums text-slate-900">{{ $results['average'] }}%</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Mean of {{ $results['subjects']->count() }} subject
                            {{ Str::plural('average', $results['subjects']->count()) }}, from
                            {{ $results['assessments'] }} approved {{ Str::plural('mark', $results['assessments']) }}.
                        </p>
                    </div>

                    <div class="text-right">
                        @if ($results['grade'])
                            <p class="font-display text-2xl font-bold text-slate-900">{{ $results['grade'] }}</p>
                        @endif
                        <x-ui.badge :tone="$results['passed'] ? 'success' : 'danger'">
                            {{ $results['passed'] ? 'Above the pass mark' : 'Below the pass mark' }} ({{ $passMark }}%)
                        </x-ui.badge>
                    </div>
                </div>
            </x-ui.card>

            @if ($canEdit)
                <p class="text-xs text-slate-500">
                    To correct an approved mark, open the assessment from
                    <a href="{{ route('grades.approvals') }}" class="font-medium text-brand hover:underline">grade approvals</a>.
                    Every correction is recorded in the audit trail with its reason.
                </p>
            @endif
        </div>
    @endif
</x-layouts.app>
