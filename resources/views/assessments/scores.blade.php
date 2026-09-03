<x-layouts.app :title="$assessment->title" heading="Enter marks">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Assessments' => route('assessments.index'),
        $assessment->title => null,
    ]" />

    <x-ui.page-header
        :title="$assessment->title"
        :description="$assessment->subject?->name.' · '.$assessment->section?->full_name.' · marked out of '.$assessment->max_score"
    >
        <x-slot:actions>
            <x-ui.status-badge :status="$assessment->status" />

            @can('enterScores', $assessment)
                <x-ui.button :href="route('assessments.marks.import', $assessment)" variant="secondary">Upload marks</x-ui.button>
            @endcan

            @can('update', $assessment)
                <x-ui.button :href="route('assessments.edit', $assessment)" variant="secondary">Edit details</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- A rejected sheet comes back with the reviewer's note attached. --}}
    @if ($assessment->status === 'rejected' && $assessment->review_note)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4" role="alert">
            <p class="text-sm font-semibold text-amber-900">Sent back for correction</p>
            <p class="mt-1 text-sm text-amber-800">{{ $assessment->review_note }}</p>
        </div>
    @endif

    @if ($assessment->status === 'approved')
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-semibold text-emerald-900">These marks are official</p>
            <p class="mt-1 text-sm text-emerald-800">
                Approved {{ $assessment->approved_at?->diffForHumans() }}. They now count towards report cards
                and are visible to parents.
            </p>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Marks entered" :value="$summary['entered'].' of '.($summary['entered'] + $summary['missing'])" note="Students marked" />
        <x-ui.stat label="Class average" :value="$summary['average'] === null ? '—' : $summary['average'].' / '.$assessment->max_score" note="Mean mark" />
        <x-ui.stat label="Highest" :value="$summary['highest'] === null ? '—' : (string) $summary['highest']" note="Top mark" />
        <x-ui.stat label="Pass rate" :value="$summary['passRate'] === null ? '—' : $summary['passRate'].'%'" note="At 60% or above" />
    </div>

    <form method="POST" action="{{ route('assessments.scores.save', $assessment) }}" class="mt-6">
        @csrf
        @method('PUT')

        <x-ui.card :padded="false">
            @if ($students->isEmpty())
                <x-ui.empty-state
                    icon="◉"
                    title="No students in this class"
                    description="Enroll students into this section before entering marks."
                />
            @else
                <x-ui.table :headings="array_filter(['Student', 'Number', 'Mark (out of '.$assessment->max_score.')', $submissions->isNotEmpty() || $assessment->type === 'assignment' ? 'Handed in' : null, 'Grade', 'Comment'])">
                    @foreach ($students as $student)
                        @php
                            $existing = $scores[$student->id] ?? null;
                            $value = old("scores.{$student->id}", $existing?->score);
                            $percentage = ($value !== null && $value !== '' && $assessment->max_score)
                                ? ((float) $value / $assessment->max_score) * 100
                                : null;
                            $band = $percentage === null
                                ? null
                                : $scale->first(fn ($b) => $percentage >= $b->min_score && $percentage <= $b->max_score);
                        @endphp

                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5 font-medium text-slate-900">{{ $student->full_name }}</td>
                            <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{{ $student->student_number }}</td>

                            <td class="px-5 py-2.5">
                                @if ($canEnter)
                                    <input
                                        type="number"
                                        name="scores[{{ $student->id }}]"
                                        value="{{ $value }}"
                                        step="0.01"
                                        min="0"
                                        max="{{ $assessment->max_score }}"
                                        inputmode="decimal"
                                        aria-label="Mark for {{ $student->full_name }}"
                                        class="w-28 rounded-lg border-0 px-3 py-1.5 text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand"
                                    >
                                @else
                                    <span class="tabular-nums text-slate-900">{{ $value ?? '—' }}</span>
                                @endif

                                @error("scores.{$student->id}")
                                    <span class="mt-1 block text-xs font-medium text-rose-600">{{ $message }}</span>
                                @enderror
                            </td>

                            @if ($assessment->type === 'assignment')
                                <td class="px-5 py-2.5">
                                    @php $submission = $submissions[$student->id] ?? null; @endphp

                                    @if ($submission)
                                        <div class="flex items-center gap-2">
                                            <x-ui.badge :tone="$submission->isLate() ? 'warning' : 'success'">
                                                {{ $submission->isLate() ? 'Late' : 'On time' }}
                                            </x-ui.badge>

                                            @if ($submission->attachment_path)
                                                <a href="{{ route('submissions.download', $submission) }}"
                                                   class="text-xs font-medium text-brand hover:underline">File</a>
                                            @endif
                                        </div>

                                        @if ($submission->body)
                                            <details class="mt-1">
                                                <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700">
                                                    Read answer
                                                </summary>
                                                <p class="mt-1 max-w-sm whitespace-pre-line rounded-lg bg-slate-50 p-2 text-xs text-slate-700">{{ $submission->body }}</p>
                                            </details>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">Not handed in</span>
                                    @endif
                                </td>
                            @endif

                            <td class="px-5 py-2.5">
                                @if ($band)
                                    <x-ui.badge :tone="in_array($band->grade, ['A', 'B']) ? 'success' : ($band->min_score < 60 ? 'danger' : 'warning')">
                                        {{ $band->grade }}
                                    </x-ui.badge>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-5 py-2.5">
                                @if ($canEnter)
                                    <input
                                        type="text"
                                        name="remarks[{{ $student->id }}]"
                                        value="{{ old("remarks.{$student->id}", $existing?->remark) }}"
                                        maxlength="255"
                                        placeholder="Optional"
                                        aria-label="Comment for {{ $student->full_name }}"
                                        class="w-full min-w-40 rounded-lg border-0 px-3 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand"
                                    >
                                @else
                                    <span class="text-sm text-slate-600">{{ $existing?->remark ?: '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>

                @if ($canEnter)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                        <p class="text-xs text-slate-500">
                            Leave a mark blank to remove it. Marks stay editable until you submit them.
                        </p>

                        <div class="flex items-center gap-2">
                            <x-ui.button type="submit" variant="secondary">Save marks</x-ui.button>

                            <x-ui.confirm
                                :action="route('assessments.submit', $assessment)"
                                title="Submit these marks for approval?"
                                message="You will not be able to change them while the academic office reviews them. They can be sent back to you if anything needs correcting."
                                confirm="Submit for approval"
                                variant="primary"
                                class="bg-brand px-3.5 py-2 text-sm font-semibold text-white hover:opacity-90"
                            >Submit for approval</x-ui.confirm>
                        </div>
                    </div>
                @endif
            @endif
        </x-ui.card>
    </form>

    @error('scores')
        <p class="mt-3 text-sm font-medium text-rose-600">{{ $message }}</p>
    @enderror

    @if ($canApprove)
        <x-ui.card class="mt-6" title="Approval" description="These marks are waiting for a decision.">
            <div class="flex flex-wrap gap-2">
                <x-ui.button :href="route('grades.review', $assessment)">Review and decide</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</x-layouts.app>
