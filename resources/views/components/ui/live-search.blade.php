@props([
    'name' => 'search',
    'value' => null,
    'placeholder' => 'Search…',
    'label' => 'Search',
])

{{--
    A search box that filters as you type.

    It is an ordinary text input inside an ordinary GET form, so with
    JavaScript off it still works exactly as it always did: type, press the
    button, get results. The Alpine below only removes the button press.

    Two details make it usable rather than merely clever.

    The page reload would normally take the cursor with it, so the field
    re-focuses itself and puts the caret back at the end - otherwise the second
    letter you type lands nowhere and the whole feature is worse than the
    button was.

    And it waits for a pause in typing before submitting. Firing on every
    keystroke would queue a query per letter and leave the results racing the
    typist.
--}}
<div
    x-data="{
        submit() {
            clearTimeout(this.pending);
            this.pending = setTimeout(() => this.$refs.field.form.requestSubmit(), 350);
        },
        pending: null,
    }"
    x-init="
        /*
         * $nextTick is required, not decorative: x-init runs while this element
         * is being set up, before Alpine has walked the children and registered
         * x-ref, so reading $refs.field here directly finds nothing and the
         * focus restore silently does not happen - which is the whole feature.
         *
         * Focus is only taken when a search is already in progress, so arriving
         * at the page fresh does not yank it around for keyboard users.
         */
        $nextTick(() => {
            const field = $refs.field;

            if (field && field.value !== '') {
                field.focus();
                field.setSelectionRange(field.value.length, field.value.length);
            }
        })
    "
    {{ $attributes->merge(['class' => 'min-w-56 flex-1']) }}
>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>

    <input
        type="search"
        name="{{ $name }}"
        id="{{ $name }}"
        x-ref="field"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        x-on:input="submit()"
        {{-- Enter submits immediately rather than waiting out the debounce. --}}
        x-on:keydown.enter="clearTimeout(pending)"
        class="block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm
               ring-1 ring-inset ring-slate-300 placeholder:text-slate-400
               transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)]
               hover:ring-slate-400 focus:-translate-y-px focus:shadow-md focus:ring-2 focus:ring-inset focus:ring-brand"
    >
</div>
