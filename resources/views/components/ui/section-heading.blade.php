@props(['title', 'description' => null, 'action' => null, 'href' => null])

<div class="mb-4 flex flex-wrap items-end justify-between gap-2">
    <div>
        <h2 class="font-display text-base font-bold text-slate-900">{{ $title }}</h2>
        @if ($description)
            <p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @if ($href)
        <a href="{{ $href }}" class="group inline-flex items-center gap-1 text-sm font-semibold text-brand">
            {{ $action ?? 'View all' }}
            <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
        </a>
    @endif
</div>
