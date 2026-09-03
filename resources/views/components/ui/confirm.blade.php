@props(['action', 'method' => 'POST', 'title', 'message', 'confirm' => 'Confirm', 'variant' => 'danger'])

{{-- Destructive actions always go through a confirmation dialog. --}}
<div x-data="{ open: false }" class="inline-block">
    <button
        type="button"
        @click="open = true"
        {{ $attributes->merge(['class' => 'press inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100']) }}
    >{{ $slot }}</button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            @keydown.escape.window="open = false"
        >
            <div
                x-show="open"
                x-transition:enter="transition duration-250 ease-out"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
                @click="open = false"
            ></div>

            <div
                x-show="open"
                x-trap.noscroll="open"
                x-transition:enter="transition duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
                x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
            >
                <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $message }}</p>

                <div class="mt-6 flex justify-end gap-2">
                    <x-ui.button variant="secondary" size="sm" @click="open = false">Cancel</x-ui.button>

                    <form method="POST" action="{{ $action }}" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        @if (! in_array($method, ['GET', 'POST']))
                            @method($method)
                        @endif

                        <x-ui.button type="submit" :variant="$variant" size="sm" ::disabled="busy">
                            {{-- Spinner while the request is in flight. --}}
                            <span x-show="busy" x-cloak class="spin-soft size-3 rounded-full border-2 border-current border-t-transparent"></span>
                            <span x-text="busy ? 'Working…' : @js($confirm)">{{ $confirm }}</span>
                        </x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
