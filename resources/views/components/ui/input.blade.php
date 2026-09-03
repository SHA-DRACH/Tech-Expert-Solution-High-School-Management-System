@props(['name', 'type' => 'text', 'value' => null, 'id' => null, 'remember' => true])

{{--
    `id` defaults to `name`; pass a unique one where a page repeats the same
    form, so labels stay attached to the right field.

    `remember` re-fills the box with what the user last typed after a failed
    save. Turn it off on a page that repeats a form: the old input is keyed by
    field name alone, so one year's rejected dates would otherwise reappear
    inside every other year's form.

    A `type="password"` field gets a show/hide toggle automatically, rather than
    each call site opting in - there are ten password boxes across the app and
    the eleventh should not be able to forget.
--}}
@php
    $id = $id ?? $name;
    $isPassword = $type === 'password';
@endphp

@if ($isPassword)
    <div class="relative" x-data="{ show: false }">
@endif

<input
    type="{{ $type }}"
    @if ($isPassword)
        {{-- Alpine drives the real type; the attribute above is what a browser
             with no JavaScript is left with, which must stay `password`. --}}
        x-bind:type="show ? 'text' : 'password'"
    @endif
    name="{{ $name }}"
    id="{{ $id }}"
    value="{{ $remember ? old($name, $value) : $value }}"
    @error($name) aria-invalid="true" @enderror
    {{ $attributes->merge([
        'class' => 'block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm '
            .'ring-1 ring-inset placeholder:text-slate-400 '
            .'transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] '
            .'hover:ring-slate-400 focus:-translate-y-px focus:shadow-md focus:ring-2 focus:ring-inset focus:ring-brand '
            .($isPassword ? 'pr-11 ' : '')
            .($errors->has($name) ? 'ring-rose-400' : 'ring-slate-300'),
    ]) }}
>

@if ($isPassword)
    {{--
        x-cloak, so the button is not there at all until Alpine is running.
        A visible control that does nothing is worse than no control, and
        without JavaScript this one could not reveal anything.
    --}}
    <button
        type="button"
        x-cloak
        x-on:click="show = ! show"
        x-bind:aria-label="show ? 'Hide password' : 'Show password'"
        x-bind:aria-pressed="show"
        tabindex="-1"
        class="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-lg text-slate-400
               transition-colors hover:text-slate-600 focus-visible:text-brand focus-visible:outline
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
    >
        {{-- Open eye while hidden: pressing it shows the password. --}}
        <svg x-show="! show" class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
             stroke-width="1.6" aria-hidden="true">
            <path d="M1.5 10S4.75 4.5 10 4.5 18.5 10 18.5 10 15.25 15.5 10 15.5 1.5 10 1.5 10Z"
                  stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="10" cy="10" r="2.5" />
        </svg>

        {{-- Struck-through eye while showing: pressing it hides it again. --}}
        <svg x-show="show" x-cloak class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
             stroke-width="1.6" aria-hidden="true">
            <path d="M8.2 5.1A7.6 7.6 0 0 1 10 4.9c5.25 0 8.5 5.1 8.5 5.1a15.6 15.6 0 0 1-2.55 3.05M5.1 6.4A15.5 15.5 0 0 0 1.5 10s3.25 5.1 8.5 5.1c1.2 0 2.28-.24 3.24-.63"
                  stroke-linecap="round" stroke-linejoin="round" />
            <path d="M8.2 8.3a2.5 2.5 0 0 0 3.5 3.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M2.5 2.5l15 15" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
    </div>
@endif
