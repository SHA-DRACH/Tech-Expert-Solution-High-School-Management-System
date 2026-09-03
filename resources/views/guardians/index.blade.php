<x-layouts.app title="Parents & guardians" heading="Parents & guardians">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Parents & guardians' => null]" />

    <x-ui.page-header title="Parents & guardians" description="Family records and the children linked to them.">
        <x-slot:actions>
            @can('guardians.view')
                <x-ui.button :href="route('exports.guardians')" variant="secondary">Export CSV</x-ui.button>
            @endcan

            @can('create', App\Models\Guardian::class)
                <x-ui.button :href="route('guardians.create')">Add guardian</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-56 flex-1">
                <label for="search" class="sr-only">Search parents and guardians</label>
                <x-ui.input name="search" :value="$search" placeholder="Search by name, phone or email..." />
            </div>

            <x-ui.button type="submit" variant="secondary">Search</x-ui.button>

            @if ($search)
                <x-ui.button :href="route('guardians.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($guardians->isEmpty())
            <x-ui.empty-state
                icon="❋"
                title="{{ $search ? 'No guardians match that search' : 'No parents or guardians yet' }}"
                description="{{ $search ? 'Try a different name, phone number or email address.' : 'Add a parent or guardian, then link their children to them.' }}"
            >
                @can('create', App\Models\Guardian::class)
                    <x-ui.button :href="route('guardians.create')">Add the first guardian</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @else
            <x-ui.table :headings="['Name', 'Contact', 'Children', 'Status', '']">
                @foreach ($guardians as $guardian)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('guardians.show', $guardian) }}" class="font-medium text-slate-900 hover:text-brand hover:underline">
                                {{ $guardian->full_name }}
                            </a>
                            @if ($guardian->occupation)
                                <span class="block text-xs text-slate-500">{{ $guardian->occupation }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600">
                            {{ $guardian->phone ?: '-' }}
                            @if ($guardian->email)
                                <span class="block text-xs text-slate-500">{{ $guardian->email }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $guardian->students_count }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$guardian->status" /></td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('guardians.show', $guardian)" variant="ghost" size="sm">View</x-ui.button>

                            @can('update', $guardian)
                                <x-ui.button :href="route('guardians.edit', $guardian)" variant="ghost" size="sm">Edit</x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $guardians->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
