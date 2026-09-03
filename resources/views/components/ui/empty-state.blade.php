@props(['title' => 'Nothing here yet', 'description' => null, 'icon' => '◇'])

<div class="enter-fade flex flex-col items-center justify-center px-6 py-16 text-center">
    <span class="float-soft grid size-14 place-items-center rounded-full bg-slate-100 text-2xl text-slate-400" aria-hidden="true">
        {{ $icon }}
    </span>

    <h3 class="enter-rise stagger-1 mt-5 text-sm font-semibold text-slate-900">{{ $title }}</h3>

    @if ($description)
        <p class="enter-rise stagger-2 mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="enter-rise stagger-3 mt-5">{{ $slot }}</div>
    @endif
</div>
