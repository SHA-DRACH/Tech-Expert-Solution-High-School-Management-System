<x-layouts.app title="My report cards">
    <x-ui.page-header
        title="Report cards"
        description="Published report cards only. A card appears here once the school has released it."
    />

    @if ($cards->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="🗎"
                title="No report cards yet"
                description="Your results are published at the end of each period."
            />
        </x-ui.card>
    @else
        <x-ui.card :padded="false">
            @foreach ($cards as $card)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 last:border-0">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ $card->term?->name ?? 'Full year' }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $card->academicYear?->name }}
                            @if ($card->published_at) · published {{ $card->published_at->format('j M Y') }} @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        @if ($card->average !== null)
                            <span class="font-display text-base font-bold tabular-nums text-slate-900">{{ $card->average }}%</span>
                        @endif

                        <x-ui.button :href="route('reportcards.show', $card)" variant="ghost" size="sm">Open</x-ui.button>

                        @if ($abilities['download_report_card'] ?? false)
                            <x-ui.button :href="route('reportcards.download', $card)" variant="secondary" size="sm">Download</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </x-ui.card>
    @endif
</x-layouts.app>
