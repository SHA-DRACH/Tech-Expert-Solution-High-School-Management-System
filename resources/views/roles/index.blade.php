<x-layouts.app title="Roles & permissions" heading="Roles & permissions">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Roles & permissions' => null]" />

    <x-ui.page-header
        title="Roles & permissions"
        description="Roles decide what each account can see and do. System roles can be tuned but not removed."
    >
        <x-slot:actions>
            @can('create', App\Models\Role::class)
                <x-ui.button :href="route('roles.create')">Create role</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <x-ui.table :headings="['Role', 'Permissions', 'Accounts', '']">
            @foreach ($roles as $role)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-900">{{ $role->name }}</span>
                            @if ($role->is_system)
                                <x-ui.badge>System</x-ui.badge>
                            @endif
                        </div>
                        @if ($role->description)
                            <p class="mt-0.5 max-w-lg text-xs text-slate-500">{{ $role->description }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3 tabular-nums text-slate-600">{{ $role->permissions_count }}</td>
                    <td class="px-5 py-3 tabular-nums text-slate-600">{{ $role->users_count }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            @can('update', $role)
                                <x-ui.button :href="route('roles.edit', $role)" variant="ghost" size="sm">Edit</x-ui.button>
                            @endcan

                            @can('delete', $role)
                                <x-ui.confirm
                                    :action="route('roles.destroy', $role)"
                                    method="DELETE"
                                    title="Delete this role?"
                                    :message="'The role &quot;'.$role->name.'&quot; will be removed. This cannot be undone.'"
                                    confirm="Delete role"
                                    class="text-rose-600 hover:bg-rose-50"
                                >Delete</x-ui.confirm>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <p class="mt-4 text-xs text-slate-500">
        A role can only be deleted once no account is using it.
    </p>
</x-layouts.app>
