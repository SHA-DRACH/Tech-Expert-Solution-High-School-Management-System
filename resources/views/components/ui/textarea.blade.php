@props(['name', 'rows' => 4, 'value' => null])

<textarea
    name="{{ $name }}"
    id="{{ $name }}"
    rows="{{ $rows }}"
    {{ $attributes->merge([
        'class' => 'block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm '
            .'ring-1 ring-inset placeholder:text-slate-400 '
            .'transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] '
            .'hover:ring-slate-400 focus:shadow-md focus:ring-2 focus:ring-inset focus:ring-brand '
            .($errors->has($name) ? 'ring-rose-400' : 'ring-slate-300'),
    ]) }}
>{{ old($name, $value) }}</textarea>
