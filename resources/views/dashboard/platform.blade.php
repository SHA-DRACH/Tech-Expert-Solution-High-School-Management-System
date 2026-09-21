<x-layouts.app title="Platform overview" heading="Platform overview">
    <x-ui.page-header
        title="Platform overview"
        :description="'Every school on '.config('app.product', 'NovaxSuites').'.'"
    >
        <x-slot:actions>
            <x-ui.button :href="route('platform.schools')">Manage schools</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metrics as $metric)
            <x-ui.stat :label="$metric['label']" :value="$metric['value']" :note="$metric['note']"
                       data-aos="fade-up" data-aos-delay="{{ $loop->index * 60 }}" />
        @endforeach
    </div>

    <x-ui.card class="mt-6" title="Schools" description="Enter a school to work inside its data" :padded="false">
        <x-ui.table :headings="['School', 'Accounts', 'Students', 'Status', '']">
            @foreach ($schools as $school)
                <tr>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg font-display text-xs font-bold text-white"
                                  style="background-color: {{ $school->primary_color }}">
                                {{ $school->initials() }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900">{{ $school->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $school->motto }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 tabular-nums text-slate-600">{{ $school->users_count }}</td>
                    <td class="px-5 py-3 tabular-nums text-slate-600">{{ $school->students_count }}</td>
                    <td class="px-5 py-3"><x-ui.status-badge :status="$school->is_active ? 'active' : 'suspended'" /></td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('platform.schools.select', $school) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm">Enter</x-ui.button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</x-layouts.app>
