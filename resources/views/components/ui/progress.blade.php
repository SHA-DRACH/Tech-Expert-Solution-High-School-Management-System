@props(['value' => 0, 'label' => null, 'caption' => null, 'tone' => 'brand'])

@php
    $percent = max(0, min(100, (float) $value));
    $bar = ['brand' => 'bg-brand', 'success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500'][$tone] ?? 'bg-brand';
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label || $caption)
        <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
            @if ($label)<span class="font-medium text-slate-700">{{ $label }}</span>@endif
            @if ($caption)<span class="text-xs tabular-nums text-slate-500">{{ $caption }}</span>@endif
        </div>
    @endif

    <div class="h-2 overflow-hidden rounded-full bg-slate-100"
         role="progressbar" aria-valuenow="{{ round($percent) }}" aria-valuemin="0" aria-valuemax="100">
        {{-- Width animates from zero when the element first paints. --}}
        <div class="{{ $bar }} h-full origin-left rounded-full transition-[width] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]"
             style="width: {{ $percent }}%"></div>
    </div>
</div>
