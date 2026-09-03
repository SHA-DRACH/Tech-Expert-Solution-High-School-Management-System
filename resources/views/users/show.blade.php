@php
    $linked = match (true) {
        $account->teacherProfile !== null => ['label' => 'Teacher record', 'name' => $account->teacherProfile->full_name, 'href' => route('teachers.show', $account->teacherProfile)],
        $account->guardianProfile !== null => ['label' => 'Guardian record', 'name' => $account->guardianProfile->full_name, 'href' => route('guardians.show', $account->guardianProfile)],
        $account->studentProfile !== null => ['label' => 'Student record', 'name' => $account->studentProfile->full_name, 'href' => route('students.show', $account->studentProfile)],
        default => null,
    };
@endphp

<x-layouts.app :title="$account->name" :heading="$account->name">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Users & staff' => route('users.index'),
        $account->name => null,
    ]" />

    <x-ui.page-header :title="$account->name" :description="$account->email">
        <x-slot:actions>
            <x-ui.button :href="route('users.index')" variant="secondary">Back to accounts</x-ui.button>

            @can('update', $account)
                <x-ui.button :href="route('users.edit', $account)">Edit account</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-6">
            <x-ui.card title="Roles" description="An account's roles are the only thing that decide what it can do.">
                @if ($account->roles->isEmpty())
                    <p class="text-sm text-slate-500">
                        This account holds no role, so its holder can sign in but reach nothing.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach ($account->roles as $role)
                            <div class="rounded-lg border border-slate-200 px-4 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900">{{ $role->name }}</p>
                                    <span class="text-xs text-slate-500">
                                        {{ $role->permissions->count() }} {{ Str::plural('permission', $role->permissions->count()) }}
                                    </span>
                                </div>

                                @if ($role->description)
                                    <p class="mt-1 text-sm text-slate-500">{{ $role->description }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card
                title="What this account can do"
                description="Everything the roles above add up to. This is the list that is actually enforced."
            >
                @if ($permissions->isEmpty())
                    <p class="text-sm text-slate-500">Nothing. This account can sign in and see the sign-in page only.</p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($permissions as $permission)
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700" title="{{ $permission->slug }}">
                                {{ $permission->name }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Account">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-ui.status-badge :status="$account->status" /></dd>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Created</dt>
                        <dd class="font-medium text-slate-900">{{ $account->created_at?->format('j M Y') }}</dd>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <dt class="shrink-0 text-slate-500">Email</dt>
                        <dd class="min-w-0 break-all text-right font-medium text-slate-900">{{ $account->email }}</dd>
                    </div>
                </dl>

                @can('suspend', $account)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <x-ui.confirm
                            :action="route('users.status', $account)"
                            method="PATCH"
                            :title="$account->isActive() ? 'Suspend this account?' : 'Reactivate this account?'"
                            :message="$account->isActive()
                                ? $account->name.' will be unable to sign in until the account is reactivated. Their records and history are kept.'
                                : $account->name.' will be able to sign in again immediately.'"
                            :confirm="$account->isActive() ? 'Suspend account' : 'Reactivate account'"
                            :variant="$account->isActive() ? 'danger' : 'primary'"
                        >
                            {{ $account->isActive() ? 'Suspend account' : 'Reactivate account' }}
                        </x-ui.confirm>

                        {{--
                            There is deliberately no delete. An account is
                            attached to marks entered, payments received and
                            audit entries; removing it would leave that history
                            pointing at nobody. Suspension is the off switch.
                        --}}
                        <p class="mt-2 text-xs text-slate-500">
                            Accounts are suspended rather than deleted, so the marks, payments and audit
                            entries recorded against them still make sense.
                        </p>
                    </div>
                @endcan
            </x-ui.card>

            @if ($linked)
                <x-ui.card :title="$linked['label']">
                    <a href="{{ $linked['href'] }}" class="group flex items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-slate-900">{{ $linked['name'] }}</span>
                        <span aria-hidden="true" class="text-slate-300 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:text-brand">&rarr;</span>
                    </a>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
