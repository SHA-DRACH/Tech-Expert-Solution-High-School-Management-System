<x-layouts.app title="Announcements" heading="Announcements">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Announcements' => null]" />

    <x-ui.page-header title="Announcements" description="Notices published to parents, students and staff.">
        <x-slot:actions>
            <x-ui.button :href="route('announcements.create')">New announcement</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        @if ($announcements->isEmpty())
            <x-ui.empty-state icon="✦" title="No announcements" description="Publish your first notice to reach families.">
                <x-ui.button :href="route('announcements.create')">Write an announcement</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($announcements as $announcement)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-slate-900">{{ $announcement->title }}</p>
                                    @if ($announcement->is_emergency)
                                        <x-ui.badge tone="danger">Emergency</x-ui.badge>
                                    @endif
                                    <x-ui.badge>{{ Str::headline($announcement->category) }}</x-ui.badge>
                                </div>

                                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $announcement->body }}</p>

                                <p class="mt-1.5 text-xs text-slate-500">
                                    {{ $announcement->author?->name ?? 'System' }} ·
                                    {{ $announcement->published_at?->format('j M Y') }}
                                    @if ($announcement->section) · {{ $announcement->section->full_name }} @endif
                                </p>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($announcement->audience as $audience)
                                        <x-ui.badge tone="info">{{ Str::headline($audience) }}</x-ui.badge>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                @if ($announcement->status === 'archived')
                                    <form method="POST" action="{{ route('announcements.restore', $announcement) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="sm">Restore</x-ui.button>
                                    </form>
                                @else
                                    <a href="{{ route('announcements.edit', $announcement) }}"
                                       class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">Edit</a>

                                    <x-ui.confirm
                                        :action="route('announcements.destroy', $announcement)"
                                        method="DELETE"
                                        title="Archive this announcement?"
                                        message="It will stop appearing in the portals. Nothing is deleted."
                                        confirm="Archive"
                                    >Archive</x-ui.confirm>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-3">{{ $announcements->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
