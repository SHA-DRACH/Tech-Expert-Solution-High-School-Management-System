
<x-layouts.app title="My assignments">
    <x-ui.page-header
        title="My assignments"
        :description="$canSubmit
            ? 'Work set for your class. Submit before the due date.'
            : 'Work set for your class. Hand your work in to your teacher.'"
    />

    @if ($assignments->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="◈"
                title="Nothing set right now"
                description="Assignments your teachers set will appear here."
            />
        </x-ui.card>
    @else
        <div class="space-y-5">
            @foreach ($assignments as $assignment)
                @php
                    $submission = $submissions[$assignment->id] ?? null;
                    $overdue = $assignment->ends_at && $assignment->ends_at->isPast() && ! $submission;

                    // An assessment with a start time has not opened yet.
                    $notYetOpen = $assignment->starts_at && $assignment->starts_at->isFuture();
                    $closed = $assignment->status === 'approved';
                @endphp

                <x-ui.card data-aos="fade-up" data-aos-delay="{{ ($loop->index % 5) * 60 }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-display text-base font-bold text-slate-900">{{ $assignment->title }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ $assignment->subject?->name }}
                                @if ($assignment->teacher) · {{ $assignment->teacher->full_name }} @endif
                                · marked out of {{ $assignment->max_score }}
                            </p>

                            @if ($assignment->starts_at)
                                <p class="mt-1 text-xs text-slate-500">
                                    Opens {{ $assignment->starts_at->format('l, j F Y') }} at {{ $assignment->starts_at->format('H:i') }}
                                    @if ($notYetOpen) · not open yet @endif
                                </p>
                            @endif

                            @if ($assignment->ends_at)
                                <p @class([
                                    'mt-1 text-xs font-medium',
                                    'text-rose-600' => $overdue,
                                    'text-slate-500' => ! $overdue,
                                ])>
                                    Closes {{ $assignment->ends_at->format('l, j F Y') }} at {{ $assignment->ends_at->format('H:i') }}
                                    @if ($overdue) · overdue @endif
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($submission)
                                <x-ui.badge :tone="$submission->isLate() ? 'warning' : 'success'">
                                    {{ $submission->isLate() ? 'Submitted late' : 'Submitted' }}
                                </x-ui.badge>
                            @elseif ($overdue)
                                <x-ui.badge tone="danger">Not submitted</x-ui.badge>
                            @else
                                <x-ui.badge>Open</x-ui.badge>
                            @endif
                        </div>
                    </div>

                    {{-- What the teacher actually asked for. --}}
                    <x-assessment.question :assessment="$assignment" />

                    {{-- What was handed in --}}
                    @if ($submission)
                        <div class="mt-4 rounded-lg border-l-2 border-brand bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Your submission
                            </p>

                            @if ($submission->body)
                                <p class="mt-1.5 whitespace-pre-line text-sm text-slate-700">{{ $submission->body }}</p>
                            @endif

                            @if ($submission->attachment_path)
                                <a href="{{ route('submissions.download', $submission) }}"
                                   class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
                                    <span aria-hidden="true">🗎</span>
                                    {{ $submission->original_name ?? 'Download attachment' }}
                                </a>
                            @endif

                            <p class="mt-2 text-[11px] text-slate-400">
                                Handed in {{ $submission->submitted_at?->diffForHumans() }}
                            </p>

                            @if ($submission->teacher_note)
                                <p class="mt-2 text-sm text-amber-700">{{ $submission->teacher_note }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Submission form --}}
                    @if ($canSubmit && ! $closed)
                        <div x-data="{ open: {{ $submission ? 'false' : 'true' }} }" class="mt-4">
                            @if ($submission)
                                <x-ui.button type="button" variant="secondary" size="sm" @click="open = ! open">
                                    Replace my submission
                                </x-ui.button>
                            @endif

                            <form
                                x-show="open"
                                x-cloak
                                x-collapse
                                method="POST"
                                action="{{ route('student.assignments.submit', $assignment) }}"
                                enctype="multipart/form-data"
                                class="mt-3 space-y-4"
                            >
                                @csrf

                                <x-ui.field label="Your answer" name="body"
                                            hint="Write your answer here, attach a file, or both.">
                                    <x-ui.textarea name="body" rows="5" :value="$submission?->body" />
                                </x-ui.field>

                                <x-ui.field label="Attachment" name="attachment"
                                            hint="PDF, Word, image or text file, up to 8 MB.">
                                    <input type="file" name="attachment" id="attachment-{{ $assignment->id }}"
                                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt"
                                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                                </x-ui.field>

                                <div class="flex justify-end">
                                    <x-ui.button type="submit">
                                        {{ $submission ? 'Replace submission' : 'Submit work' }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    @elseif ($closed)
                        <p class="mt-4 text-sm text-slate-500">
                            This assignment has been marked and is now closed.
                        </p>
                    @elseif (! $canSubmit)
                        <p class="mt-4 text-sm text-slate-500">
                            Your school asks for this work to be handed in directly to your teacher.
                        </p>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
