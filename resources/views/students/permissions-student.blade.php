<x-layouts.app :title="$student->full_name" heading="Student portal permissions">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Students' => route('students.index'),
        $student->full_name => route('students.show', $student),
        'Portal permissions' => null,
    ]" />

    <x-ui.page-header
        :title="$student->full_name"
        description="Override the school defaults for this student only."
    />

    <form method="POST" action="{{ route('students.permissions.update', $student) }}" class="max-w-3xl">
        @csrf
        @method('PUT')

        <x-ui.card title="Access" description="Tick 'school default' to remove an override entirely.">
            <div class="space-y-1">
                @foreach ($abilities as $ability => $definition)
                    @php $hasOverride = $overrides->has($ability); @endphp

                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50"
                         x-data="{ useDefault: {{ $hasOverride ? 'false' : 'true' }} }">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-800">{{ $definition['label'] }}</span>
                            <span class="block font-mono text-[11px] text-slate-400">{{ $ability }}</span>
                        </span>

                        <div class="flex items-center gap-4">
                            <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="use_default[{{ $ability }}]" value="1"
                                       x-model="useDefault"
                                       class="size-4 rounded border-slate-300 text-slate-500 focus:ring-slate-400">
                                School default
                            </label>

                            <span class="relative inline-flex shrink-0 transition-opacity"
                                  :class="useDefault ? 'pointer-events-none opacity-40' : ''">
                                <input type="checkbox" name="abilities[{{ $ability }}]" value="1"
                                       class="peer sr-only" @checked($effective[$ability])>
                                <span class="block h-6 w-11 rounded-full bg-slate-200 transition-colors duration-200 peer-checked:bg-brand"></span>
                                <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 peer-checked:translate-x-5"></span>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <div class="mt-6 flex items-center justify-end gap-2">
            <x-ui.button :href="route('students.permissions')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save permissions</x-ui.button>
        </div>
    </form>
</x-layouts.app>
