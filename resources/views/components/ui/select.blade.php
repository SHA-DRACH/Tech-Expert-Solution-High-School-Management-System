@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null])

<select
    name="{{ $name }}"
    id="{{ $name }}"
    {{ $attributes->merge([
        'class' => 'block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm '
            .'ring-1 ring-inset ring-slate-300 transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] '
            .'hover:ring-slate-400 focus:shadow-md focus:ring-2 focus:ring-inset focus:ring-brand',
    ]) }}
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) old($name, $selected) === (string) $optionValue)>
            {{ $optionLabel }}
        </option>
    @endforeach

    {{ $slot }}
</select>
