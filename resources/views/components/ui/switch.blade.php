@props(['name', 'label', 'hint' => null, 'checked' => false])

{{--
    A labelled on/off control.

    The checkbox itself stays in the accessibility tree (`sr-only`, not
    `hidden`) so it is reachable by keyboard and announced by a screen reader;
    the track and knob are decoration driven by `peer-checked`.

    Remember that an unchecked box submits nothing at all. Whatever reads this
    has to treat "absent" as false rather than "leave as it was".
--}}
<label class="flex cursor-pointer items-start justify-between gap-4 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50">
    <span class="min-w-0">
        <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </span>

    <span class="relative mt-0.5 inline-flex shrink-0">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="1"
            class="peer sr-only"
            @checked($checked)
            {{ $attributes }}
        >
        <span class="block h-6 w-11 rounded-full bg-slate-200 transition-colors duration-200 peer-checked:bg-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2"></span>
        <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 peer-checked:translate-x-5"></span>
    </span>
</label>
