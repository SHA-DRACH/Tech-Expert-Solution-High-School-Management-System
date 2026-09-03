<x-layouts.app title="My exams">
    <x-ui.page-header
        title="Examinations"
        :description="$term ? 'Examinations for '.$term->name.'.' : 'Examination periods and the papers you will sit.'"
    />

    @if ($examinations->isNotEmpty())
        <x-ui.card class="mb-6" title="Examination periods" :padded="false">
            @foreach ($examinations as $examination)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ $examination->name }}</p>
                        @if ($examination->starts_on)
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $examination->starts_on->format('j M Y') }}
                                @if ($examination->ends_on) &ndash; {{ $examination->ends_on->format('j M Y') }} @endif
                            </p>
                        @endif
                    </div>

                    <x-ui.status-badge :status="$examination->status ?? 'scheduled'" />
                </div>
            @endforeach
        </x-ui.card>
    @endif

    <x-ui.card title="Your papers" :padded="false"
               description="Examination papers set for your class this term.">
        @forelse ($papers as $paper)
            @php
                $notOpen = $paper->starts_at && $paper->starts_at->isFuture();
                $closed = $paper->ends_at && $paper->ends_at->isPast();
            @endphp

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                <div class="min-w-0">
                    <p class="font-medium text-slate-900">{{ $paper->subject?->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $paper->title }} · out of {{ $paper->max_score }}
                        @if ($paper->teacher) · {{ $paper->teacher->full_name }} @endif
                    </p>
                </div>

                <div class="text-right">
                    @if ($paper->starts_at)
                        <p class="text-sm text-slate-700">
                            {{ $paper->starts_at->format('j M Y') }}
                            <span class="block text-xs text-slate-500">
                                {{ $paper->starts_at->format('H:i') }}
                                @if ($paper->ends_at) &ndash; {{ $paper->ends_at->format('H:i') }} @endif
                            </span>
                        </p>
                    @else
                        <p class="text-sm text-slate-400">Date not set</p>
                    @endif

                    @if ($notOpen)
                        <x-ui.badge>Not started</x-ui.badge>
                    @elseif ($closed)
                        <x-ui.badge tone="neutral">Finished</x-ui.badge>
                    @endif
                </div>
            </div>
        @empty
            <x-ui.empty-state
                icon="⌸"
                title="No examination papers set"
                description="Papers for your class appear here once your teachers create them."
            />
        @endforelse
    </x-ui.card>
</x-layouts.app>
