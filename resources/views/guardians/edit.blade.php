<x-layouts.app :title="'Edit '.$guardian->full_name" heading="Edit guardian">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Parents & guardians' => route('guardians.index'),
        $guardian->full_name => route('guardians.show', $guardian),
        'Edit' => null,
    ]" />

    <x-ui.page-header
        :title="'Edit '.$guardian->full_name"
        description="Contact details for this parent or guardian."
    />

    @error('guardian')
        <div class="enter-rise mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('guardians.update', $guardian) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Contact details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="First name" name="first_name" required>
                    <x-ui.input name="first_name" :value="$guardian->first_name" required autofocus />
                </x-ui.field>

                <x-ui.field label="Last name" name="last_name" required>
                    <x-ui.input name="last_name" :value="$guardian->last_name" required />
                </x-ui.field>

                <x-ui.field label="Phone number" name="phone"
                            hint="This is the number the school calls about an absence.">
                    <x-ui.input name="phone" type="tel" :value="$guardian->phone" placeholder="+231 77 000 0000" />
                </x-ui.field>

                <x-ui.field label="Email address" name="email">
                    <x-ui.input name="email" type="email" :value="$guardian->email" />
                </x-ui.field>

                <x-ui.field label="Occupation" name="occupation">
                    <x-ui.input name="occupation" :value="$guardian->occupation" />
                </x-ui.field>

                <x-ui.field label="Home address" name="address" class="sm:col-span-2">
                    <x-ui.textarea name="address" rows="3" :value="$guardian->address" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                @can('archive', $guardian)
                    <x-ui.confirm
                        :action="route('guardians.destroy', $guardian)"
                        method="DELETE"
                        title="Archive this guardian?"
                        :message="$guardian->full_name.' will be removed from the active list. Their record is kept and can be restored. This is refused if they are still the only contact for a child.'"
                        confirm="Archive guardian"
                    >Archive guardian</x-ui.confirm>
                @endcan
            </div>

            <div class="flex items-center gap-2">
                <x-ui.button :href="route('guardians.show', $guardian)" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
