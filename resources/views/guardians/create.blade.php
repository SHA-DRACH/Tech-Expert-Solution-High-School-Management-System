<x-layouts.app title="Add guardian" heading="Add guardian">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Parents & guardians' => route('guardians.index'), 'Add guardian' => null]" />

    <x-ui.page-header title="Add a parent or guardian" description="Children can be linked to this record afterwards." />

    <form method="POST" action="{{ route('guardians.store') }}" class="max-w-3xl space-y-6">
        @csrf

        <x-ui.card title="Contact details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="First name" name="first_name" required>
                    <x-ui.input name="first_name" required autofocus />
                </x-ui.field>

                <x-ui.field label="Last name" name="last_name" required>
                    <x-ui.input name="last_name" required />
                </x-ui.field>

                <x-ui.field label="Phone number" name="phone">
                    <x-ui.input name="phone" type="tel" placeholder="+231 77 000 0000" />
                </x-ui.field>

                <x-ui.field label="Email address" name="email" hint="Used later to invite them to the parent portal.">
                    <x-ui.input name="email" type="email" />
                </x-ui.field>

                <x-ui.field label="Occupation" name="occupation">
                    <x-ui.input name="occupation" />
                </x-ui.field>

                <x-ui.field label="Home address" name="address" class="sm:col-span-2">
                    <x-ui.textarea name="address" rows="3" />
                </x-ui.field>
            </div>
        </x-ui.card>

        @if ($roles->isNotEmpty())
            <x-forms.portal-access
                :roles="$roles"
                :default-role="$defaultRole"
                who="this parent or guardian"
            />
        @endif

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('guardians.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Add guardian</x-ui.button>
        </div>
    </form>
</x-layouts.app>
