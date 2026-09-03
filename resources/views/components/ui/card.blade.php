@props([
    'title' => null,
    'description' => null,
    'actions' => null,
    'padded' => true,
    'enter' => 'enter-rise',
    'hover' => false,
])

<section {{ $attributes->merge([
    'class' => 'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm '
        .'transition-shadow duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] '
        .($hover ? 'lift ' : 'hover:shadow-md ')
        .$enter,
]) }}>
    @if ($title || $actions)
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>
            @if ($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endif
        </header>
    @endif

    <div class="{{ $padded ? 'px-5 py-4' : '' }}">{{ $slot }}</div>
</section>
