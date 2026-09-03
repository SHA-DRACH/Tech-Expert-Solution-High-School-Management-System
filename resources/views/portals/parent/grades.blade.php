@php
    use App\Support\Money;

@endphp
<x-layouts.app title="Grades">
    <x-ui.page-header title="Grades" :description="$child->full_name.' · approved marks only'" />

    <x-portal.child-selector :children="$children" :selected="$child" route="parent.grades" />

    {{--
        Results by term, which is how a parent thinks about them, and how the
        school reports them. Everything below counts approved marks only: a mark
        a teacher has entered but the academic office has not signed off may
        still change, and showing it here would be telling a family a result
        that is not yet a result.
    --}}
    @if ($terms->isNotEmpty())
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            @foreach ($progress as $item)
                <a href="{{ route('parent.grades', ['child' => $child->id, 'term' => $item['term']->id]) }}"
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
                            ? 'Results not published yet'
                            : ($item['grade'] ? 'Grade '.$item['grade'] : 'Average') }}
                    </p>
                </a>
            @endforeach
        </div>

        <x-ui.card class="mb-6" :padded="false"
                   :title="$term ? $term->name.' results' : 'Results'"
                   description="One row per subject, with the average across every approved mark in that subject.">
            @if ($results === null || $results['subjects']->isEmpty())
                <x-ui.empty-state
                    icon="◇"
                    title="No results published yet"
                    description="Marks appear here once the school has approved them."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left">
                                <th scope="col" class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">Subject</th>
                                <th scope="col" class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">Marks counted</th>
                                <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Average</th>
                                <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Grade</th>
                                <th scope="col" class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">Teacher</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results['subjects'] as $subject)
                                <tr class="border-b border-slate-100">
                                    <td class="px-5 py-2.5 font-medium text-slate-900">{{ $subject['subject']->name }}</td>
                                    <td class="px-3 py-2.5 tabular-nums text-slate-500">{{ $subject['assessments']->count() }}</td>
                                    <td @class([
                                        'px-3 py-2.5 text-center font-semibold tabular-nums',
                                        'text-rose-600' => $subject['passed'] === false,
                                        'text-slate-900' => $subject['passed'] !== false,
                                    ])>{{ $subject['average'] }}%</td>
                                    <td class="px-3 py-2.5 text-center font-semibold text-slate-900">{{ $subject['grade'] ?? '—' }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $subject['teacher']?->full_name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 bg-slate-50">
                                <th scope="row" class="px-5 py-3 text-left text-sm font-semibold text-slate-900">
                                    {{ $term?->name }} average
                                </th>
                                <td></td>
                                <td class="px-3 py-3 text-center font-display text-base font-bold tabular-nums text-slate-900">
                                    {{ $results['average'] }}%
                                </td>
                                <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ $results['grade'] ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-ui.badge :tone="$results['passed'] ? 'success' : 'danger'">
                                        {{ $results['passed'] ? 'Above the pass mark' : 'Below the pass mark' }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    A subject average is weighted by how much each piece of work counts for. The overall average is
                    the mean of the subject averages. The school's pass mark is {{ $passMark }}%.
                </p>
            @endif
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Assessment results" :padded="false">
            @forelse ($grades as $grade)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $grade->subject }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $grade->title }} · {{ Str::headline($grade->type) }} ·
                            {{ $grade->recordedOn?->format('j M Y') }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <span class="text-xs tabular-nums text-slate-500">{{ $grade->percentage }}%</span>
                        <span class="font-display text-sm font-bold tabular-nums text-slate-900">
                            {{ rtrim(rtrim((string) $grade->score, '0'), '.') }}<span class="text-slate-400">/{{ $grade->max }}</span>
                        </span>
                        @if ($grade->grade)
                            <x-ui.badge :tone="in_array($grade->grade, ['A', 'B']) ? 'success' : ($grade->grade === 'F' ? 'danger' : 'warning')">
                                {{ $grade->grade }}
                            </x-ui.badge>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="◈" title="No grades yet" description="Approved marks will appear here." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Report cards" description="Published periods" :padded="false">
            @forelse ($reportCards as $card)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div>
                        <a href="{{ route('reportcards.show', $card) }}"
                           class="text-sm font-medium text-slate-900 hover:text-brand hover:underline">
                            {{ $card->term?->name ?? 'Full year' }}
                        </a>
                        <p class="text-xs text-slate-500">
                            Average {{ $card->average ?? '—' }}%{{ $card->position ? ' · position '.$card->position : '' }}
                        </p>
                    </div>
                    <x-ui.button :href="route('reportcards.show', $card)" variant="ghost" size="sm">Open</x-ui.button>
                </div>
            @empty
                <x-ui.empty-state icon="🗎" title="None published" description="Report cards appear at the end of each period." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
