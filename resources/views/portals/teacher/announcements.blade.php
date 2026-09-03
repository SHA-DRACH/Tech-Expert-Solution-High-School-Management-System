<x-layouts.app title="Announcements">
    <x-ui.page-header
        title="Announcements"
        description="Notices from the school office addressed to teaching staff."
    />

    @if ($announcements->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="✦"
                title="Nothing announced"
                description="Announcements addressed to teachers appear here."
            />
        </x-ui.card>
    @else
        <div class="space-y-4">
            @foreach ($announcements as $announcement)
                <x-ui.card :padded="false">
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="font-display text-base font-bold text-slate-900">{{ $announcement->title }}</h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $announcement->published_at?->format('j F Y, H:i') }}
                                    @if ($announcement->section)
                                        · {{ $announcement->section->full_name }}
                                    @endif
                                </p>
                            </div>

                            <span class="flex shrink-0 items-center gap-1.5">
                                @if ($announcement->is_emergency)
                                    <x-ui.badge tone="danger">Urgent</x-ui.badge>
                                @endif
                                <x-ui.badge>{{ Str::headline($announcement->category) }}</x-ui.badge>
                            </span>
                        </div>

                        <div class="mt-3 space-y-3 text-sm text-slate-700">
                            @foreach (preg_split('/\n\s*\n/', trim($announcement->body)) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="mt-6">{{ $announcements->links() }}</div>
    @endif
</x-layouts.app>
