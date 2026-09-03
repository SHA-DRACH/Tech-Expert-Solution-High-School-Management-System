<x-layouts.app title="My grades">
    <x-ui.page-header title="My grades" description="Only marks your school has approved." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Assessment results" :padded="false">
            @forelse ($grades as $grade)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $grade->subject }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $grade->title }} · {{ Str::headline($grade->type) }} · {{ $grade->recordedOn?->format('j M Y') }}
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

        @if ($abilities['view_report_cards'])
            <x-ui.card title="Report cards" :padded="false">
                @forelse ($reportCards as $card)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                        <div>
                            <a href="{{ route('reportcards.show', $card) }}"
                               class="text-sm font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $card->term?->name ?? 'Full year' }}
                            </a>
                            <p class="text-xs text-slate-500">Average {{ $card->average ?? '—' }}%</p>
                        </div>
                        <x-ui.button :href="route('reportcards.show', $card)" variant="ghost" size="sm">Open</x-ui.button>
                    </div>
                @empty
                    <x-ui.empty-state icon="🗎" title="None published" />
                @endforelse
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
