{{-- Session flash messages: slide up, hold, then fade away on their own. --}}
@if (session('status') || $errors->any())
    <div
        x-data="{ show: false }"
        x-init="$nextTick(() => show = true); setTimeout(() => show = false, 6500)"
        x-show="show"
        x-cloak
        x-transition:enter="transition duration-400 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
        x-transition:enter-start="opacity-0 translate-y-6 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition duration-250 ease-in"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-95"
        class="fixed inset-x-4 bottom-4 z-50 sm:left-auto sm:right-6 sm:w-96"
        role="status"
        aria-live="polite"
    >
        @if (session('status'))
            <div class="relative flex items-start gap-3 overflow-hidden rounded-xl border border-emerald-200 bg-white p-4 shadow-xl shadow-emerald-900/5">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs text-emerald-700" aria-hidden="true">&check;</span>
                <p class="flex-1 text-sm text-slate-700">{{ session('status') }}</p>
                <button type="button" @click="show = false" class="press text-slate-400 hover:text-slate-600" aria-label="Dismiss">&times;</button>

                {{-- Countdown bar showing how long the message stays. --}}
                <span aria-hidden="true" class="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-emerald-400"
                      x-init="$el.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }], { duration: 6500, easing: 'linear', fill: 'forwards' })"></span>
            </div>
        @elseif ($errors->any())
            <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-white p-4 shadow-xl shadow-rose-900/5">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-rose-100 text-xs text-rose-700" aria-hidden="true">!</span>
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-800">Please check the form</p>
                    <p class="mt-0.5 text-sm text-slate-600">{{ $errors->first() }}</p>
                </div>
                <button type="button" @click="show = false" class="press text-slate-400 hover:text-slate-600" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
@endif
