@php
    use App\Services\PeriodGrades;

    $column = $periodNumber ? 'p'.$periodNumber : null;
@endphp

<x-layouts.app title="Grade sheets & report cards" heading="Grade sheets & report cards">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Grade sheets & report cards' => null]" />

    <x-ui.page-header title="Grade sheets & report cards"
                      description="A grade sheet for every period, and the periodic progress report at the end of the year. Approved marks only.">
        @if ($section && $period)
            <x-slot:actions>
                <x-ui.button :href="route('progress.grade-sheets', ['section' => $section->id, 'period' => $period->id])" variant="secondary">
                    Print all grade sheets
                </x-ui.button>
                <x-ui.button :href="route('progress.report-cards', ['section' => $section->id])">
                    Print all report cards
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.card class="mb-6" :padded="false">
        <form method="GET" x-data x-on:change="$el.requestSubmit()" class="flex flex-wrap items-end gap-3 px-5 py-4">
            <div class="w-52">
                <label for="section" class="mb-1 block text-xs font-medium text-slate-600">Class</label>
                <x-ui.select name="section" :selected="$section?->id"
                             :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
            </div>
            <div class="w-52">
                <label for="period" class="mb-1 block text-xs font-medium text-slate-600">Period</label>
                <x-ui.select name="period" :selected="$period?->id"
                             :options="$periods->mapWithKeys(fn ($p, $n) => [$p->id => ucfirst(App\Models\Term::periodName($n))])->all()" />
            </div>
            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
        </form>
    </x-ui.card>

    @if ($periods->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="▤" title="This year is not set up in periods yet"
                              description="Grade sheets are printed per period. Set the year up in Academic years & periods." />
        </x-ui.card>
    @elseif (! $report)
        <x-ui.card>
            <x-ui.empty-state icon="⌘" title="Choose a class" />
        </x-ui.card>
    @else
        <form method="POST" action="{{ route('progress.conduct', $section) }}">
            @csrf
            <input type="hidden" name="period" value="{{ $period?->id }}">

            <x-ui.card :padded="false"
                       :title="$section->full_name.' · '.ucfirst($period?->label() ?? '')"
                       :description="'Class sponsor: '.($section->classTeacher?->full_name ?? 'not assigned').' · '.$report['classSize'].' '.Str::plural('student', $report['classSize'])">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <th class="px-5 py-2.5">Student</th>
                                <th class="px-5 py-2.5 text-center">Period average</th>
                                <th class="px-5 py-2.5 text-center">Rank</th>
                                <th class="px-5 py-2.5 text-center">Present / absent</th>
                                <th class="px-5 py-2.5">Conduct</th>
                                <th class="px-5 py-2.5"><span class="sr-only">Documents</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['students']->sortBy(fn ($r) => $r['ranks'][$column] ?? PHP_INT_MAX) as $row)
                                @php
                                    $student = $row['student'];
                                    $days = $row['attendance'][$periodNumber] ?? null;
                                @endphp
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="px-5 py-2.5">
                                        <span class="block font-medium text-slate-900">{{ $student->full_name }}</span>
                                        <span class="block font-mono text-xs text-slate-500">{{ $student->student_number }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-center tabular-nums">
                                        {{ PeriodGrades::format($row['averages'][$column] ?? null) }}
                                    </td>
                                    <td class="px-5 py-2.5 text-center tabular-nums">
                                        {{ isset($row['ranks'][$column]) ? $row['ranks'][$column].' of '.$report['classSize'] : '—' }}
                                    </td>
                                    <td class="px-5 py-2.5 text-center tabular-nums text-slate-600">
                                        {{ $days ? $days['present'].' / '.$days['absent'] : '—' }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        @if ($canRecordConduct)
                                            <select name="conduct[{{ $student->id }}]" aria-label="Conduct for {{ $student->full_name }}"
                                                    class="rounded-md border-0 py-1 pl-2 pr-8 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                                <option value="">—</option>
                                                @foreach ($conductOptions as $option)
                                                    <option @selected(($row['conduct'][$periodNumber] ?? null) === $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{ $row['conduct'][$periodNumber] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <x-ui.button size="sm" variant="ghost"
                                                     :href="route('progress.grade-sheets', ['section' => $section->id, 'period' => $period?->id, 'student' => $student->id])">
                                            Grade sheet
                                        </x-ui.button>
                                        <x-ui.button size="sm" variant="ghost"
                                                     :href="route('progress.report-cards', ['section' => $section->id, 'student' => $student->id])">
                                            Report card
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canRecordConduct)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
                        <p class="text-xs text-slate-500">Conduct is written by the class sponsor and printed on the period's grade sheet.</p>
                        <x-ui.button type="submit" size="sm">Save conduct</x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        </form>

        <p class="mt-3 text-xs text-slate-500">
            An average and rank appear once every subject a student takes has an approved grade for the period.
        </p>
    @endif
</x-layouts.app>
