@php
    $anyEditable = $editable->isNotEmpty();
@endphp

<x-layouts.app title="Mark sheet" heading="Mark sheet">
    <x-ui.breadcrumbs :trail="['Overview' => route('portal'), 'Mark sheet' => null]" />

    <x-ui.page-header
        title="Mark sheet"
        description="Every student in a class, against every assessment for one subject. Enter a whole class at once, or one student on their own."
    />

    <x-ui.card class="mb-6" :padded="false">
        <form method="GET"
              x-data
              x-on:change="$el.requestSubmit()"
              class="flex flex-wrap items-end gap-3 px-5 py-4">
            <div class="w-52">
                <label for="section" class="sr-only">Class</label>
                <x-ui.select name="section" :selected="$section?->id"
                             :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
            </div>

            <div class="w-52">
                <label for="subject" class="sr-only">Subject</label>
                <x-ui.select name="subject" :selected="$subject?->id"
                             :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" />
            </div>

            <div class="w-40">
                <label for="term" class="sr-only">Term</label>
                <x-ui.select name="term" :selected="$term?->id"
                             :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>

            <p class="ml-auto text-xs text-slate-500">Pass mark {{ $passMark }}%</p>
        </form>
    </x-ui.card>

    @if ($sections->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="⌘"
                title="You have no classes to mark"
                description="A teacher can only mark a class and subject they are assigned to. Ask the academic office to assign your teaching."
            />
        </x-ui.card>
    @elseif ($subjectsUnavailable)
        {{-- Two very different problems that look identical from here, so both
             are named rather than showing one empty table. --}}
        <x-ui.card>
            <x-ui.empty-state
                icon="◈"
                title="No subjects for {{ $section->full_name }}"
                description="Either this class has no subjects attached to it yet, or you are not assigned to teach any of them. The first is fixed in Academic structure, the second in Teaching assignments."
            />
        </x-ui.card>
    @elseif ($assessments->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="◇"
                title="No assessments for {{ $subject?->name }} in {{ $term?->name }}"
                description="Marks are recorded against an assessment — a test, an exam or a piece of work. Create one first."
            >
                @can('grades.enter')
                    <x-ui.button :href="route('assessments.create')">Create an assessment</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @elseif ($students->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="◉" title="No students enrolled in {{ $section->full_name }}" />
        </x-ui.card>
    @else
        <form method="POST" action="{{ route('marks.store') }}">
            @csrf

            <div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
                <x-ui.card class="min-w-0" :padded="false"
                           :title="$section->full_name.' · '.$subject->name"
                           :description="$students->count().' '.Str::plural('student', $students->count()).' · '.$assessments->count().' '.Str::plural('assessment', $assessments->count())">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-left">
                                    <th scope="col" class="sticky left-0 z-10 bg-slate-50 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Student
                                    </th>

                                    @foreach ($assessments as $assessment)
                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold text-slate-600">
                                            <span class="block truncate" title="{{ $assessment->title }}">
                                                {{ Str::limit($assessment->title, 16) }}
                                            </span>
                                            <span class="mt-0.5 block font-normal normal-case text-slate-400">
                                                out of {{ $assessment->max_score }}
                                            </span>

                                            @if (! $editable->contains($assessment->id))
                                                {{-- Named, so a teacher knows why the column is
                                                     read-only rather than thinking it is broken. --}}
                                                <span class="mt-1 block font-normal normal-case text-amber-700">
                                                    {{ $assessment->status === 'approved' ? 'approved' : 'submitted' }}
                                                </span>
                                            @endif
                                        </th>
                                    @endforeach

                                    <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Average
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($students as $student)
                                    @php
                                        $percentages = collect();

                                        foreach ($assessments as $a) {
                                            $s = $scores->get($a->id.'.'.$student->id);

                                            if ($s && $a->max_score > 0) {
                                                $percentages->push(((float) $s->score / $a->max_score) * 100);
                                            }
                                        }

                                        $average = $percentages->isEmpty() ? null : round($percentages->avg(), 1);
                                    @endphp

                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        <td class="sticky left-0 z-10 bg-white px-5 py-2 hover:bg-slate-50">
                                            <span class="block font-medium text-slate-900">{{ $student->full_name }}</span>
                                            <span class="block text-xs text-slate-400">{{ $student->student_number }}</span>
                                        </td>

                                        @foreach ($assessments as $assessment)
                                            @php
                                                $score = $scores->get($assessment->id.'.'.$student->id);
                                                $canEdit = $editable->contains($assessment->id);
                                            @endphp

                                            <td class="px-3 py-2 text-center">
                                                @if ($canEdit)
                                                    <input
                                                        type="number"
                                                        name="marks[{{ $assessment->id }}][{{ $student->id }}]"
                                                        value="{{ $score?->score !== null ? rtrim(rtrim(number_format((float) $score->score, 2, '.', ''), '0'), '.') : '' }}"
                                                        min="0"
                                                        max="{{ $assessment->max_score }}"
                                                        step="0.01"
                                                        inputmode="decimal"
                                                        aria-label="{{ $student->full_name }} — {{ $assessment->title }}"
                                                        class="w-20 rounded-lg border-0 px-2 py-1.5 text-center text-sm tabular-nums text-slate-900
                                                               ring-1 ring-inset ring-slate-300 transition
                                                               hover:ring-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand"
                                                    >
                                                @else
                                                    <span class="tabular-nums text-slate-500">
                                                        {{ $score?->score !== null ? rtrim(rtrim(number_format((float) $score->score, 2, '.', ''), '0'), '.') : '—' }}
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td @class([
                                            'px-3 py-2 text-center font-semibold tabular-nums',
                                            'text-rose-600' => $average !== null && $average < $passMark,
                                            'text-slate-900' => $average === null || $average >= $passMark,
                                        ])>{{ $average !== null ? $average.'%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($anyEditable)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3.5">
                            <p class="text-xs text-slate-500">
                                A blank box means no mark recorded — which is not the same as zero, and is not
                                stored as one. Clearing a box removes the mark.
                            </p>

                            <x-ui.button type="submit">Save marks</x-ui.button>
                        </div>
                    @else
                        <p class="border-t border-slate-100 px-5 py-3.5 text-xs text-amber-700">
                            Every assessment here has been submitted or approved, so the marks are locked.
                            @can('grades.approve')
                                You can still correct them — reject the assessment to return it to the teacher,
                                or edit a mark directly.
                            @else
                                Ask the academic office to reject one if a mark needs changing.
                            @endcan
                        </p>
                    @endif
                </x-ui.card>

                {{-- One student, one assessment. Posts the same shape as the grid. --}}
                <div class="xl:sticky xl:top-6 xl:self-start">
                    <x-ui.card title="One student at a time"
                               description="Pick a student and an assessment and put the mark straight in.">
                        @if ($anyEditable)
                            <div x-data="{ student: '', assessment: '' }" class="space-y-4">
                                <x-ui.field label="Student" name="single_student">
                                    <select x-model="student" id="single_student"
                                            class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                        <option value="">Select a student</option>
                                        @foreach ($students as $student)
                                            <option value="{{ $student->id }}">{{ $student->full_name }}</option>
                                        @endforeach
                                    </select>
                                </x-ui.field>

                                <x-ui.field label="Assessment" name="single_assessment">
                                    <select x-model="assessment" id="single_assessment"
                                            class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                        <option value="">Select an assessment</option>
                                        @foreach ($assessments as $assessment)
                                            @if ($editable->contains($assessment->id))
                                                <option value="{{ $assessment->id }}">
                                                    {{ $assessment->title }} (out of {{ $assessment->max_score }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </x-ui.field>

                                {{--
                                    Scrolls to the matching box in the grid and focuses it, rather
                                    than posting a second time. One save path, one source of truth
                                    for what is on screen, and the person sees the mark land in the
                                    table where they will look for it later.
                                --}}
                                <x-ui.button
                                    type="button"
                                    class="w-full"
                                    x-bind:disabled="! student || ! assessment"
                                    x-on:click="
                                        const box = document.getElementsByName('marks[' + assessment + '][' + student + ']')[0];
                                        if (box) {
                                            box.scrollIntoView({ block: 'center', behavior: 'smooth' });
                                            box.focus();
                                            box.select();
                                        }
                                    "
                                >Go to that mark</x-ui.button>

                                <p class="text-xs text-slate-500">
                                    This takes you to the right box in the sheet. Type the mark, then
                                    <span class="font-medium">Save marks</span>.
                                </p>
                            </div>
                        @else
                            <p class="text-sm text-slate-500">
                                Nothing here can be changed at the moment.
                            </p>
                        @endif
                    </x-ui.card>

                    @if ($canApprove && $assessments->contains(fn ($a) => $a->status === 'approved'))
                        <x-ui.card class="mt-5" title="Correcting an approved mark">
                            <x-ui.field label="Reason" name="reason"
                                        hint="Kept with the change in the audit trail. A grade altered after approval is something a parent may ask about.">
                                <x-ui.textarea name="reason" rows="2"
                                               placeholder="Transcription error on the paper register." />
                            </x-ui.field>
                        </x-ui.card>
                    @endif
                </div>
            </div>
        </form>
    @endif
</x-layouts.app>
