<x-layouts.app title="Assignments">
    <x-ui.page-header
        title="Assignments"
        :description="$child->full_name.' · what has been set, and the question itself'"
    />

    <x-portal.child-selector :children="$children" :selected="$child" route="parent.assignments" />

    @if ($assignments->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="🗎"
                title="Nothing set yet"
                description="Work set for this class appears here, with the question the teacher wrote."
            />
        </x-ui.card>
    @else
        <div class="space-y-5">
            @foreach ($assignments as $assignment)
                @php
                    $submission = $submissions->get($assignment->id);
                    $overdue = $assignment->ends_at && $assignment->ends_at->isPast() && ! $submission;
                @endphp

                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-display text-base font-bold text-slate-900">{{ $assignment->title }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ $assignment->subject?->name }}
                                @if ($assignment->teacher) · {{ $assignment->teacher->full_name }} @endif
                                · marked out of {{ $assignment->max_score }}
                            </p>

                            @if ($assignment->ends_at)
                                <p @class([
                                    'mt-1 text-xs font-medium',
                                    'text-rose-600' => $overdue,
                                    'text-slate-500' => ! $overdue,
                                ])>
                                    Closes {{ $assignment->ends_at->format('l, j F Y') }}
                                    at {{ $assignment->ends_at->format('H:i') }}
                                    @if ($overdue) · not handed in @endif
                                </p>
                            @endif
                        </div>

                        <div class="shrink-0">
                            @if ($submission)
                                <x-ui.badge :tone="$submission->isLate() ? 'warning' : 'success'">
                                    {{ $submission->isLate() ? 'Handed in late' : 'Handed in' }}
                                </x-ui.badge>
                            @elseif ($overdue)
                                <x-ui.badge tone="danger">Not handed in</x-ui.badge>
                            @else
                                <x-ui.badge>Open</x-ui.badge>
                            @endif
                        </div>
                    </div>

                    {{-- The question, so a parent can actually help with it. --}}
                    <x-assessment.question :assessment="$assignment" />

                    @if ($submission?->submitted_at)
                        <p class="mt-3 text-xs text-slate-500">
                            Handed in {{ $submission->submitted_at->format('j M Y, H:i') }}.
                        </p>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
