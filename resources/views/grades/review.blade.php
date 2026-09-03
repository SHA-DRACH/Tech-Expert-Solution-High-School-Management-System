<x-layouts.app title="Review marks" heading="Review marks">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Grade approvals' => route('grades.approvals'),
        $assessment->title => null,
    ]" />

    <x-ui.page-header
        :title="$assessment->title"
        :description="$assessment->subject?->name.' · '.$assessment->section?->full_name.' · by '.($assessment->teacher?->full_name ?? 'unknown')"
    >
        <x-slot:actions>
            <x-ui.status-badge :status="$assessment->status" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Marks submitted" :padded="false">
            <x-ui.table :headings="['Student', 'Number', 'Mark', 'Percentage', 'Grade', 'Comment']">
                @foreach ($scores as $score)
                    @php
                        $percentage = $score->percentage();
                        $band = $percentage === null
                            ? null
                            : $scale->first(fn ($b) => $percentage >= $b->min_score && $percentage <= $b->max_score);
                    @endphp

                    <tr>
                        <td class="px-5 py-2.5 font-medium text-slate-900">{{ $score->student?->full_name }}</td>
                        <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{{ $score->student?->student_number }}</td>
                        <td class="px-5 py-2.5 tabular-nums text-slate-900">
                            {{ rtrim(rtrim((string) $score->score, '0'), '.') }}<span class="text-slate-400">/{{ $assessment->max_score }}</span>
                        </td>
                        <td class="px-5 py-2.5 tabular-nums text-slate-600">{{ $percentage }}%</td>
                        <td class="px-5 py-2.5">
                            @if ($band)
                                <x-ui.badge :tone="$band->min_score >= 80 ? 'success' : ($band->min_score < 60 ? 'danger' : 'warning')">
                                    {{ $band->grade }}
                                </x-ui.badge>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-sm text-slate-600">{{ $score->remark ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card title="Grade distribution">
                <x-ui.bar-chart :series="$distribution" aria-label="Number of students in each grade band" />
            </x-ui.card>

            <x-ui.card title="Decision" description="Approving makes these marks official.">
                <div class="space-y-3">
                    <form method="POST" action="{{ route('grades.approve', $assessment) }}">
                        @csrf
                        <x-ui.button type="submit" class="w-full">Approve marks</x-ui.button>
                    </form>

                    <div x-data="{ open: false }">
                        <x-ui.button type="button" variant="secondary" class="w-full" @click="open = ! open">
                            Send back for correction
                        </x-ui.button>

                        <form x-show="open" x-cloak x-collapse method="POST"
                              action="{{ route('grades.reject', $assessment) }}" class="mt-3 space-y-3">
                            @csrf

                            <x-ui.field label="What needs correcting?" name="review_note" required>
                                <x-ui.textarea name="review_note" rows="4" required
                                               placeholder="Explain what the teacher should change." />
                            </x-ui.field>

                            <x-ui.button type="submit" variant="danger" class="w-full">Send back</x-ui.button>
                        </form>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
