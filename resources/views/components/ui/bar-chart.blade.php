@props(['series' => [], 'suffix' => '', 'height' => 'h-48'])

@php
    $peak = max(array_map(fn ($point) => $point['value'], $series ?: [['value' => 0]])) ?: 1;
@endphp

<div class="{{ $height }} flex items-end justify-between gap-2 sm:gap-3" role="img"
     aria-label="{{ $attributes->get('aria-label', 'Bar chart') }}">
    @foreach ($series as $index => $point)
        <div class="group flex flex-1 flex-col items-center gap-2">
            <span class="text-xs font-semibold tabular-nums text-slate-600 transition-colors group-hover:text-brand">
                {{ $point['value'] }}{{ $suffix }}
            </span>

            <div class="grow-bar w-full rounded-t-md bg-brand/80 transition-colors duration-200 group-hover:bg-brand"
                 style="--bar-index: {{ $index }}; height: {{ max(4, round($point['value'] / $peak * 100)) }}%"></div>

            <span class="text-[11px] text-slate-500">{{ $point['label'] }}</span>
        </div>
    @endforeach
</div>
