<x-layouts.app title="Users & staff" heading="Users & staff">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Users & staff' => null]" />

    <x-ui.page-header title="Users & staff" description="Every account that can sign in to this school's workspace, and the roles it holds.">
        <x-slot:actions>
            <x-ui.button :href="route('exports.users', request()->query())" variant="secondary">Export CSV</x-ui.button>

            @can('create', App\Models\User::class)
                <x-ui.button :href="route('users.create')">Create account</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Who holds what, at a glance, before scrolling a paginated list. --}}
    @if ($roleCounts->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($roleCounts as $role)
                <a href="{{ route('users.index', ['role' => $role->slug]) }}"
                   @class([
                       'group rounded-lg border px-3.5 py-2 text-sm transition-colors',
                       'border-brand bg-brand/8 text-brand' => $filters['role'] === $role->slug,
                       'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50' => $filters['role'] !== $role->slug,
                   ])>
                    <span class="font-medium">{{ $role->name }}</span>
                    <span class="ml-1.5 tabular-nums opacity-60">{{ $role->users_count }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <x-ui.card :padded="false">
        {{-- Changing a dropdown filters straight away; the button stays for anyone
                 without JavaScript. Restricted to selects so it does not fire a second
                 time when the debounced search box loses focus. --}}
        <form method="GET"
              x-data
              x-on:change="$event.target.tagName === 'SELECT' && $el.requestSubmit()"
              class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <x-ui.live-search
                label="Search users"
                :value="$filters['search']"
                placeholder="Name, email, account or staff number…"
            />

            <div class="w-44">
                <label for="role" class="sr-only">Role</label>
                <x-ui.select
                    name="role"
                    placeholder="All roles"
                    :selected="$filters['role']"
                    :options="$roles->pluck('name', 'slug')->all()"
                />
            </div>

            <div class="w-40">
                <label for="status" class="sr-only">Status</label>
                <x-ui.select
                    name="status"
                    placeholder="Any status"
                    :selected="$filters['status']"
                    :options="['active' => 'Active', 'suspended' => 'Suspended']"
                />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('users.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($users->isEmpty())
            <x-ui.empty-state
                icon="◌"
                title="{{ array_filter($filters) ? 'No accounts match those filters' : 'No accounts yet' }}"
                description="{{ array_filter($filters) ? 'Try a different name, role or status.' : 'Create accounts for staff, parents and students so they can sign in.' }}"
            >
                @can('create', App\Models\User::class)
                    <x-ui.button :href="route('users.create')">Create the first account</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.table :headings="['Name', 'Email', 'Roles', 'Status', '']">
                @foreach ($users as $account)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-900">
                            <a href="{{ route('users.show', $account) }}" class="hover:text-brand hover:underline">
                                {{ $account->name }}
                            </a>
                            @if ($account->is(auth()->user()))
                                <span class="ml-1 text-xs font-normal text-slate-400">(you)</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $account->email }}</td>
                        <td class="px-5 py-3">
                            <span class="flex flex-wrap gap-1">
                                @forelse ($account->roles as $role)
                                    <x-ui.badge tone="info">{{ $role->name }}</x-ui.badge>
                                @empty
                                    <span class="text-xs text-slate-400">No role</span>
                                @endforelse
                            </span>
                        </td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$account->status" /></td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('users.show', $account) }}"
                                   class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">View</a>

                                @can('update', $account)
                                    <a href="{{ route('users.edit', $account) }}"
                                       class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100">Edit</a>
                                @endcan

                                @can('suspend', $account)
                                    <x-ui.confirm
                                        :action="route('users.status', $account)"
                                        method="PATCH"
                                        :title="$account->isActive() ? 'Suspend this account?' : 'Reactivate this account?'"
                                        :message="$account->isActive()
                                            ? $account->name.' will be signed out and unable to sign in again until the account is reactivated.'
                                            : $account->name.' will be able to sign in again immediately.'"
                                        :confirm="$account->isActive() ? 'Suspend account' : 'Reactivate account'"
                                        :variant="$account->isActive() ? 'danger' : 'primary'"
                                    >
                                        {{ $account->isActive() ? 'Suspend' : 'Reactivate' }}
                                    </x-ui.confirm>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $users->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
