<x-layouts.app title="Gradebook" heading="Gradebook">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Gradebook' => null]" />

    <x-ui.page-header
        title="Gradebook"
        description="Every approved result for a class and term, with each student's average and position."
    />

    @if ($terms->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="▤"
                title="No terms have been set up"
                description="Results are filed against a term. Set up the academic calendar first."
            >
                @can('academics.manage')
                    <x-ui.button :href="route('settings.years.index')">Set up the calendar</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padded="false">
            <form method="GET"
                  x-data
                  x-on:change="$el.requestSubmit()"
                  class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
                <div class="w-56">
                    <label for="section" class="sr-only">Class</label>
                    <x-ui.select
                        name="section"
                        :selected="$section?->id"
                        :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()"
                    />
                </div>

                <div class="w-44">
                    <label for="term" class="sr-only">Term</label>
                    <x-ui.select
                        name="term"
                        :selected="$term?->id"
                        :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()"
                    />
                </div>

                <x-ui.button type="submit" variant="secondary">Show</x-ui.button>

                <p class="ml-auto text-xs text-slate-500">
                    Pass mark {{ $passMark }}% · approved marks only
                </p>
            </form>

            {{-- Surfaced rather than silently reconciled: the administrator is
                 the only one who can decide which of the two figures is right. --}}
            @if ($scaleDisagrees)
                <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">
                    Your grade scale and your pass-mark setting disagree. The scale is being used
                    ({{ $passMark }}%), because it is what appears on report cards.
                    @can('exams.manage')
                        <a href="{{ route('examinations.index') }}" class="font-semibold underline underline-offset-2">Check the scale</a>
                        or
                        <a href="{{ route('settings.group.edit', 'academics') }}" class="font-semibold underline underline-offset-2">the setting</a>.
                    @endcan
                </div>
            @endif

            @if ($section === null || $term === null)
                <x-ui.empty-state title="Choose a class and a term" />
            @elseif ($rows->isEmpty())
                <x-ui.empty-state
                    icon="◇"
                    title="No students in {{ $section->full_name }}"
                    description="Enrol students into this class to see their results here."
                />
            @else
                @if ($awaiting > 0)
                    {{-- Said plainly, so an incomplete table is never mistaken for a complete one. --}}
                    <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">
                        {{ $awaiting }} {{ Str::plural('assessment', $awaiting) }} for this class and term
                        {{ $awaiting === 1 ? 'is' : 'are' }} not approved yet, so
                        {{ $awaiting === 1 ? 'its marks are' : 'their marks are' }} not counted below.
                        @can('grades.approve')
                            <a href="{{ route('grades.approvals') }}" class="font-semibold underline underline-offset-2">Review them</a>.
                        @endcan
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left">
                                <th scope="col" class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">Student</th>

                                @foreach ($subjects as $subject)
                                    <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600"
                                        title="{{ $subject->name }}">
                                        {{ $subject->code ?: Str::limit($subject->name, 10) }}
                                    </th>
                                @endforeach

                                <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Average</th>
                                <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Grade</th>
                                <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">Position</th>
                                <th scope="col" class="px-5 py-2.5"></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $row)
                                @php $bySubject = $row['subjects']->keyBy(fn ($s) => $s['subject']->id); @endphp

                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="px-5 py-2.5">
                                        <a href="{{ route('gradebook.student', ['student' => $row['student'], 'term' => $term->id]) }}"
                                           class="font-medium text-slate-900 hover:text-brand hover:underline">
                                            {{ $row['student']->full_name }}
                                        </a>
                                        <span class="block text-xs text-slate-400">{{ $row['student']->student_number }}</span>
                                    </td>

                                    @foreach ($subjects as $subject)
                                        @php $cell = $bySubject->get($subject->id); @endphp
                                        <td @class([
                                            'px-3 py-2.5 text-center tabular-nums',
                                            'text-rose-600 font-medium' => $cell && $cell['passed'] === false,
                                            'text-slate-700' => ! $cell || $cell['passed'] !== false,
                                        ])>
                                            {{ $cell ? $cell['average'].'%' : '—' }}
                                        </td>
                                    @endforeach

                                    <td @class([
                                        'px-3 py-2.5 text-center font-semibold tabular-nums',
                                        'text-rose-600' => $row['passed'] === false,
                                        'text-slate-900' => $row['passed'] !== false,
                                    ])>
                                        {{ $row['average'] !== null ? $row['average'].'%' : '—' }}
                                    </td>

                                    <td class="px-3 py-2.5 text-center font-semibold text-slate-900">{{ $row['grade'] ?? '—' }}</td>

                                    <td class="px-3 py-2.5 text-center tabular-nums text-slate-600">
                                        {{ $row['position'] ? $row['position'].' of '.$row['class_size'] : '—' }}
                                    </td>

                                    <td class="px-5 py-2.5 text-right">
                                        <a href="{{ route('gradebook.student', ['student' => $row['student'], 'term' => $term->id]) }}"
                                           class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    A subject average is weighted by each assessment's weight. The overall average is the mean of the
                    subject averages, so a subject assessed six times does not count six times as heavily as one
                    assessed twice. Students on the same average share a position.
                </p>
            @endif
        </x-ui.card>
    @endif
</x-layouts.app>
