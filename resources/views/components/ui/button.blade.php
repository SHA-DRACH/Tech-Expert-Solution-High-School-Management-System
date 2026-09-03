@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'size' => 'md'])

@php
    $variants = [
        'primary' => 'bg-brand text-white shadow-sm hover:brightness-110 hover:shadow-md focus-visible:outline-brand',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 shadow-sm hover:border-slate-300 hover:bg-slate-50 hover:shadow focus-visible:outline-slate-400',
        'danger' => 'border border-rose-200 bg-white text-rose-600 shadow-sm hover:border-rose-300 hover:bg-rose-50 focus-visible:outline-rose-400',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
    ];

    $classes = 'group/btn press relative inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold '
        .'transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] '
        .'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '
        .'disabled:pointer-events-none disabled:opacity-60 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
