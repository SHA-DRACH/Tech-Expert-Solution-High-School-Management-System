@props(['label', 'name', 'required' => false, 'hint' => null, 'id' => null])

{{--
    `id` defaults to `name`, which is right for the common case of one form per
    page. Where a page repeats the same form - one per academic year, say - the
    caller must pass a unique `id`, or every label on the page points at the
    first form's field and the rest become unreachable by click or by label.
--}}
@php $id = $id ?? $name; @endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    <div class="mt-1.5">{{ $slot }}</div>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif

    @error($name)
        {{-- Validation messages slide in rather than jumping the layout. --}}
        <p class="enter-rise mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</div>
