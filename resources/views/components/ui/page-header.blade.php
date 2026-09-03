@props(['title', 'description' => null, 'actions' => null])

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="enter-rise text-xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
        @if ($description)
            <p class="enter-rise stagger-1 mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @if ($actions)
        <div class="enter-rise stagger-2 flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
