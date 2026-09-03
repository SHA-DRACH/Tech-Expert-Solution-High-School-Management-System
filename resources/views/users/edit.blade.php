@php
    $held = old('roles', $account->roles->pluck('id')->all());
@endphp

<x-layouts.app :title="'Edit '.$account->name" heading="Edit account">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Users & staff' => route('users.index'),
        $account->name => route('users.show', $account),
        'Edit' => null,
    ]" />

    <x-ui.page-header
        :title="'Edit '.$account->name"
        description="Change the details on this account, or the roles that decide what it can reach."
    />

    <form method="POST" action="{{ route('users.update', $account) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Full name" name="name" required>
                    <x-ui.input name="name" :value="$account->name" required />
                </x-ui.field>

                <x-ui.field label="Email address" name="email" required hint="This is what they sign in with.">
                    <x-ui.input name="email" type="email" :value="$account->email" required />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card
            title="Roles"
            description="At least one. An account's roles are the only thing that decides what it can do."
        >
            @if ($roles->isEmpty())
                <p class="text-sm text-slate-500">
                    This school has no roles yet.
                    <a href="{{ route('roles.index') }}" class="font-medium text-brand hover:underline">Create one first.</a>
                </p>
            @else
                <div class="space-y-1">
                    @foreach ($roles as $role)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->id }}"
                                id="role-{{ $role->id }}"
                                class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                @checked(in_array($role->id, $held))
                            >

                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-800">{{ $role->name }}</span>
                                @if ($role->description)
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $role->description }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            @error('roles')
                <p class="enter-rise mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </x-ui.card>

        <x-ui.card
            title="Password"
            description="Leave both boxes empty to keep the current password. Filling them in replaces it immediately."
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="New password" name="password" hint="At least 12 characters.">
                    <x-ui.input name="password" type="password" autocomplete="new-password" :remember="false" />
                </x-ui.field>

                <x-ui.field label="Confirm new password" name="password_confirmation">
                    <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" :remember="false" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('users.show', $account)" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save account</x-ui.button>
        </div>
    </form>
</x-layouts.app>
