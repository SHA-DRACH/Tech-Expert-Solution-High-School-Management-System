@props(['label', 'value', 'note' => null, 'suffix' => null, 'prefix' => null])

@php
    // Numeric values count up when the card scrolls into view; anything else
    // (a dash, a range) is printed as it is.
    $numeric = is_numeric(str_replace(',', '', (string) $value))
        ? (int) str_replace(',', '', (string) $value)
        : null;
@endphp

<div {{ $attributes->merge(['class' => 'card-soft group relative overflow-hidden p-5']) }}>
    {{-- A brand wash that warms up on hover. --}}
    <span
        aria-hidden="true"
        class="pointer-events-none absolute -right-8 -top-8 size-24 rounded-full bg-brand/5 opacity-0 transition-opacity duration-500 group-hover:opacity-100"
    ></span>

    <p class="relative text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>

    <p class="relative mt-2 font-display text-3xl font-bold tabular-nums text-slate-900">
        @if ($numeric !== null)
            <span
                data-counter="{{ $numeric }}"
                @if ($suffix) data-suffix="{{ $suffix }}" @endif
                @if ($prefix) data-prefix="{{ $prefix }}" @endif
            >{{ $value }}</span>
        @else
            {{ $value }}
        @endif
    </p>

    @if ($note)
        <p class="relative mt-1 text-xs text-slate-500">{{ $note }}</p>
    @endif
</div>
