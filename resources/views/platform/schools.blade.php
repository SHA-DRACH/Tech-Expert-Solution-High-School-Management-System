<x-layouts.app title="Platform schools" heading="Platform schools">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Platform schools' => null]" />

    <x-ui.page-header
        title="Schools on the platform"
        description="Enter a school to work inside its data. You only ever see one school at a time."
    >
        <x-slot:actions>
            @if (app(App\Support\SchoolContext::class)->hasSchool())
                <form method="POST" action="{{ route('platform.schools.clear') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">Return to platform level</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        @if ($schools->isEmpty())
            <x-ui.empty-state icon="⬡" title="No schools yet" description="Onboard the first school to get started." />
        @else
            <x-ui.table :headings="['School', 'Contact', 'Accounts', 'Students', 'Status', '']">
                @foreach ($schools as $school)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg text-xs font-bold text-white"
                                      style="background-color: {{ $school->primary_color }}">
                                    {{ $school->initials() }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900">{{ $school->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $school->motto }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-slate-600">
                            {{ $school->email ?: '—' }}
                            @if ($school->phone)
                                <span class="block text-xs text-slate-500">{{ $school->phone }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $school->users_count }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $school->students_count }}</td>
                        <td class="px-5 py-3">
                            <x-ui.status-badge :status="$school->is_active ? 'active' : 'suspended'" />
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('platform.schools.select', $school) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">Enter workspace</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $schools->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
