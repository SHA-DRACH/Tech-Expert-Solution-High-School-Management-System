<x-layouts.app title="Notifications" heading="Notifications">
    <x-ui.page-header
        title="Notifications"
        :description="$unreadCount > 0 ? $unreadCount.' unread' : 'You are all caught up'"
    >
        <x-slot:actions>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">Mark all read</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <div class="flex gap-1 border-b border-slate-100 px-5 py-3">
            @foreach (['' => 'All', 'unread' => 'Unread'] as $key => $label)
                <a href="{{ route('notifications.index', $key ? ['filter' => $key] : []) }}"
                   @class([
                       'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                       'bg-brand/10 text-brand' => $filter === $key,
                       'text-slate-600 hover:bg-slate-50' => $filter !== $key,
                   ])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($notifications->isEmpty())
            <x-ui.empty-state
                icon="🔔"
                title="{{ $filter === 'unread' ? 'Nothing unread' : 'No notifications yet' }}"
                description="Admissions, grades, report cards, fees and messages all appear here."
            />
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($notifications as $notification)
                    <div @class([
                        'flex items-start gap-3 px-5 py-4 transition-colors',
                        'bg-brand/[0.03]' => $notification->read_at === null,
                    ])>
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand/8 text-sm text-brand" aria-hidden="true">
                            {{ $notification->data['icon'] ?? '•' }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('notifications.read', $notification->id) }}"
                               class="block text-sm font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $notification->data['title'] ?? 'Notification' }}
                            </a>
                            <p class="mt-0.5 text-sm text-slate-600">{{ $notification->data['body'] ?? '' }}</p>
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ $notification->data['category'] ?? 'General' }} ·
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            @if ($notification->read_at === null)
                                <x-ui.badge tone="info">New</x-ui.badge>
                            @endif

                            <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="grid size-7 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                        aria-label="Remove notification">&times;</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-3">{{ $notifications->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
