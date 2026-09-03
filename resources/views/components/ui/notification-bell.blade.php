@php
    $user = auth()->user();

    // Kept small on purpose: the bell shows the latest few and links onward.
    $recent = $user->unreadNotifications()->take(6)->get();
    $unread = $user->unreadNotifications()->count();
@endphp

<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        @click="open = ! open"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        class="press relative grid size-9 place-items-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100"
        aria-haspopup="menu"
        :aria-expanded="open"
        aria-label="{{ $unread > 0 ? $unread.' unread notifications' : 'Notifications' }}"
    >
        <span aria-hidden="true" class="text-base">🔔</span>

        @if ($unread > 0)
            <span class="absolute -right-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 z-50 mt-2 w-80 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl sm:w-96"
        role="menu"
    >
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-semibold text-slate-900">Notifications</p>

            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand hover:underline">
                        Mark all read
                    </button>
                </form>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse ($recent as $notification)
                <a
                    href="{{ route('notifications.read', $notification->id) }}"
                    class="flex gap-3 border-b border-slate-100 px-4 py-3 transition-colors last:border-0 hover:bg-slate-50"
                    role="menuitem"
                >
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand/8 text-sm text-brand" aria-hidden="true">
                        {{ $notification->data['icon'] ?? '•' }}
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-900">
                            {{ $notification->data['title'] ?? 'Notification' }}
                        </span>
                        <span class="mt-0.5 block line-clamp-2 text-xs text-slate-500">
                            {{ $notification->data['body'] ?? '' }}
                        </span>
                        <span class="mt-1 block text-[11px] text-slate-400">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                    </span>
                </a>
            @empty
                <div class="px-4 py-10 text-center">
                    <span class="grid size-10 mx-auto place-items-center rounded-full bg-slate-100 text-slate-400" aria-hidden="true">🔔</span>
                    <p class="mt-3 text-sm font-medium text-slate-700">You are all caught up</p>
                    <p class="mt-0.5 text-xs text-slate-500">New notifications will appear here.</p>
                </div>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}"
           class="block border-t border-slate-100 px-4 py-2.5 text-center text-sm font-medium text-brand hover:bg-slate-50">
            View all notifications
        </a>
    </div>
</div>
