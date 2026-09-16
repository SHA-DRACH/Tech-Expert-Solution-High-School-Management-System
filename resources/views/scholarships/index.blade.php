<x-layouts.app title="Scholarships" heading="Scholarships">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Scholarships' => null]" />

    <x-ui.page-header title="Scholarships and fee waivers"
                      description="Students whose fees are covered in part or in full.">
        <x-slot:actions>
            @can('scholarships.manage')
                <x-ui.button :href="route('scholarships.create')">Award a scholarship</x-ui.button>
            @endcan
            @can('payments.view')
                <x-ui.button :href="route('invoices.index')" variant="secondary">Invoices</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-56 flex-1">
                <label for="search" class="sr-only">Search scholarships</label>
                <x-ui.input name="search" :value="$filters['search']"
                            placeholder="Search by student, award, sponsor or reference..." />
            </div>

            <div class="w-44">
                <label for="status" class="sr-only">Status</label>
                <x-ui.select name="status" :selected="$filters['status']" placeholder="All statuses"
                             :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('scholarships.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($scholarships->isEmpty())
            <x-ui.empty-state icon="◇" title="No scholarships"
                              description="Award one and it is applied automatically the next time fees are raised." />
        @else
            <x-ui.table :headings="['Student', 'Class', 'Award', 'Covers', 'Period', 'Status', '']">
                @foreach ($scholarships as $scholarship)
                    <tr>
                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-900">{{ $scholarship->student?->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-500">{{ $scholarship->student?->student_number }}</span>
                        </td>

                        {{-- The class is on the row because "which Mary Doe?"
                             is the first question anyone asks of this list. --}}
                        <td class="whitespace-nowrap px-5 py-3 text-sm text-slate-700">
                            {{ $scholarship->student?->currentEnrollment?->section?->full_name ?? '—' }}
                        </td>

                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-900">{{ $scholarship->name }}</span>
                            @if ($scholarship->sponsor)
                                <span class="block text-xs text-slate-500">{{ $scholarship->sponsor }}</span>
                            @endif
                            @if ($scholarship->reference)
                                <span class="block font-mono text-xs text-slate-400">{{ $scholarship->reference }}</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-5 py-3 font-medium tabular-nums text-emerald-700">
                            {{ $scholarship->awardLabel() }}
                        </td>

                        <td class="px-5 py-3 text-sm text-slate-600">
                            {{ $scholarship->academicYear?->name ?? 'Every year' }}
                            <span class="block text-xs text-slate-500">
                                {{ $scholarship->term?->name ?? 'All terms' }}
                            </span>
                        </td>

                        <td class="px-5 py-3"><x-ui.status-badge :status="$scholarship->status" /></td>

                        <td class="px-5 py-3 text-right">
                            @can('scholarships.manage')
                                <div class="flex justify-end gap-2">
                                    <x-ui.button :href="route('scholarships.edit', $scholarship)" variant="ghost" size="sm">Edit</x-ui.button>

                                    @if ($scholarship->status !== 'ended')
                                        {{-- Ended, never deleted: the invoices it
                                             already discounted still have to be
                                             explainable. --}}
                                        <form method="POST" action="{{ route('scholarships.end', $scholarship) }}"
                                              onsubmit="return confirm('End this scholarship? Fees raised from now on will be charged in full.')">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.button type="submit" variant="ghost" size="sm">End</x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $scholarships->links() }}</div>
        @endif
    </x-ui.card>

    <p class="mt-4 text-xs text-slate-500">
        {{ $activeCount }} active {{ Str::plural('award', $activeCount) }}.
        An award is applied when fees are raised, and shows on the invoice as a discount.
    </p>
</x-layouts.app>
