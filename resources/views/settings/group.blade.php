@php
    use App\Services\SchoolSettings;

    $weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /*
     | Field names are `settings[key]` so the whole tab arrives as one array,
     | but old input and validation errors are looked up in dot form
     | (`settings.key`) because that is how Laravel indexes them. Getting these
     | two the wrong way round loses what the user typed on a failed save.
     */
    $field = fn (string $key) => 'settings.'.$key;
    $input = fn (string $key) => 'settings['.$key.']';

    $inputClasses = 'block w-full rounded-lg border-0 px-3 py-2 text-sm text-slate-900 shadow-sm '
        .'ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 '
        .'transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] '
        .'hover:ring-slate-400 focus:shadow-md focus:ring-2 focus:ring-inset focus:ring-brand';
@endphp

<x-layouts.app :title="$definition['label']" :heading="$definition['label']">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Settings' => route('settings.index'),
        $definition['label'] => null,
    ]" />

    <x-ui.page-header :title="$definition['label']" :description="$definition['description']" />

    {{-- The other tabs, so moving between them does not go via the hub. --}}
    <nav class="mb-6 flex flex-wrap gap-1 border-b border-slate-200 pb-px" aria-label="Settings sections">
        @foreach (SchoolSettings::GROUPS as $key => $other)
            <a href="{{ route('settings.group.edit', $key) }}"
               @class([
                   'rounded-t-lg px-3.5 py-2 text-sm font-medium transition-colors',
                   'border-b-2 border-brand text-brand' => $key === $group,
                   'border-b-2 border-transparent text-slate-500 hover:text-slate-800' => $key !== $group,
               ])
               @if ($key === $group) aria-current="page" @endif>
                {{ $other['label'] }}
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('settings.group.update', $group) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card>
            <div class="space-y-6">
                @foreach ($definition['settings'] as $key => $setting)
                    @if ($setting['type'] === SchoolSettings::TYPE_BOOLEAN)
                        <div class="-mx-3 border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                            <x-ui.switch
                                :name="$input($key)"
                                :label="$setting['label']"
                                :hint="$setting['hint'] ?? null"
                                :checked="(bool) old($field($key), $values[$key])"
                            />
                        </div>

                    @elseif ($setting['type'] === SchoolSettings::TYPE_WEEKDAYS)
                        @php $selected = (array) old($field($key), $values[$key]); @endphp

                        <x-ui.field :label="$setting['label']" :name="$field($key)" :hint="$setting['hint'] ?? null">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($weekdays as $number => $day)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="{{ $input($key) }}[]" value="{{ $number }}"
                                               class="peer sr-only" @checked(in_array($number, $selected))>
                                        <span class="block rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600
                                                     transition-colors peer-checked:border-brand peer-checked:bg-brand/8 peer-checked:text-brand
                                                     peer-focus-visible:ring-2 peer-focus-visible:ring-brand hover:bg-slate-50">
                                            {{ $day }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>

                    @elseif ($setting['type'] === SchoolSettings::TYPE_LIST)
                        <x-ui.field :label="$setting['label']" :name="$field($key)" :hint="$setting['hint'] ?? null">
                            <textarea
                                name="{{ $input($key) }}"
                                id="{{ $field($key) }}"
                                rows="6"
                                class="{{ $inputClasses }}"
                            >{{ old($field($key), implode("\n", (array) $values[$key])) }}</textarea>
                        </x-ui.field>

                    @elseif ($setting['type'] === SchoolSettings::TYPE_TEXT)
                        <x-ui.field :label="$setting['label']" :name="$field($key)" :hint="$setting['hint'] ?? null">
                            <textarea
                                name="{{ $input($key) }}"
                                id="{{ $field($key) }}"
                                rows="3"
                                class="{{ $inputClasses }}"
                            >{{ old($field($key), $values[$key]) }}</textarea>
                        </x-ui.field>

                    @else
                        <x-ui.field :label="$setting['label']" :name="$field($key)" :hint="$setting['hint'] ?? null">
                            <div class="flex items-center gap-2">
                                <input
                                    type="{{ $setting['type'] === SchoolSettings::TYPE_INTEGER ? 'number' : 'text' }}"
                                    name="{{ $input($key) }}"
                                    id="{{ $field($key) }}"
                                    value="{{ old($field($key), $values[$key]) }}"
                                    class="{{ $inputClasses }} {{ $setting['type'] === SchoolSettings::TYPE_INTEGER ? 'max-w-32' : 'max-w-xs' }}"
                                >
                                @if (! empty($setting['suffix']))
                                    <span class="text-sm text-slate-500">{{ $setting['suffix'] }}</span>
                                @endif
                            </div>
                        </x-ui.field>
                    @endif
                @endforeach
            </div>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('settings.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save {{ Str::lower($definition['label']) }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
