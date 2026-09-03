@props(['role' => null, 'catalogue', 'assigned', 'action', 'method' => 'POST'])

@php
    $assigned = old('permissions', $assigned);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" x-data="{
    selected: {{ Js::from(array_values((array) $assigned)) }},
    toggleGroup(slugs, on) {
        this.selected = on
            ? [...new Set([...this.selected, ...slugs])]
            : this.selected.filter(slug => ! slugs.includes(slug));
    },
    groupState(slugs) {
        const count = slugs.filter(slug => this.selected.includes(slug)).length;
        return count === 0 ? 'none' : (count === slugs.length ? 'all' : 'some');
    },
}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-ui.card title="Role details">
        <div class="grid gap-5">
            <x-ui.field label="Role name" name="name" required>
                <x-ui.input name="name" :value="$role?->name" required autofocus placeholder="e.g. Admissions Officer" />
            </x-ui.field>

            <x-ui.field label="Description" name="description" hint="A short note on what this role is for.">
                <x-ui.textarea name="description" :value="$role?->description" rows="2" />
            </x-ui.field>
        </div>
    </x-ui.card>

    <x-ui.card
        title="Permissions"
        description="Tick everything this role should be able to do."
        :padded="false"
    >
        <x-slot:actions>
            <span class="text-xs text-slate-500">
                <span x-text="selected.length"></span> selected
            </span>
        </x-slot:actions>

        <div class="divide-y divide-slate-100">
            @foreach ($catalogue as $group => $permissions)
                @php $slugs = array_keys($permissions); @endphp

                <fieldset class="px-5 py-4" x-data="{ slugs: {{ Js::from($slugs) }} }">
                    <legend class="sr-only">{{ $group }}</legend>

                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-800">{{ $group }}</p>

                        <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-500">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                :checked="groupState(slugs) === 'all'"
                                :indeterminate="groupState(slugs) === 'some'"
                                @change="toggleGroup(slugs, $event.target.checked)"
                            >
                            Select all
                        </label>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($permissions as $slug => $label)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $slug }}"
                                    x-model="selected"
                                    class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                >
                                <span class="min-w-0">
                                    <span class="block text-sm text-slate-700">{{ $label }}</span>
                                    <span class="block font-mono text-[11px] text-slate-400">{{ $slug }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
    </x-ui.card>

    <div class="flex items-center justify-end gap-2">
        <x-ui.button :href="route('roles.index')" variant="secondary">Cancel</x-ui.button>
        <x-ui.button type="submit">{{ $role ? 'Save changes' : 'Create role' }}</x-ui.button>
    </div>
</form>
