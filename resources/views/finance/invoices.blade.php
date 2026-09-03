@php use App\Support\Money; @endphp

<x-layouts.app title="Invoices" heading="Invoices">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Invoices' => null]" />

    <x-ui.page-header title="Invoices" description="Fees raised against students this year.">
        <x-slot:actions>
            @can('payments.record')
                <x-ui.button :href="route('payments.create')">Record payment</x-ui.button>
            @endcan
            <x-ui.button :href="route('payments.index')" variant="secondary">Payments</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Total invoiced" :value="Money::compact($totalMinor)" note="This academic year" />
        <x-ui.stat label="Collected" :value="Money::compact($collectedMinor)" note="Payments received" />
        <x-ui.stat label="Outstanding" :value="Money::compact($outstandingMinor)" note="Still to be paid" />
    </div>

    <x-ui.card class="mt-6" :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-56 flex-1">
                <label for="search" class="sr-only">Search invoices</label>
                <x-ui.input name="search" :value="$filters['search']" placeholder="Search by invoice number or student..." />
            </div>

            <div class="w-44">
                <label for="status" class="sr-only">Status</label>
                <x-ui.select name="status" :selected="$filters['status']" placeholder="All statuses"
                             :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('invoices.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($invoices->isEmpty())
            <x-ui.empty-state icon="◫" title="No invoices" description="Invoices appear here once fees are raised." />
        @else
            <x-ui.table :headings="['Invoice', 'Student', 'Total', 'Paid', 'Balance', 'Status']">
                @foreach ($invoices as $invoice)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3">
                            <span class="font-mono text-xs text-slate-700">{{ $invoice->invoice_number }}</span>
                            <span class="block text-xs text-slate-500">{{ $invoice->issued_on->format('j M Y') }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-900">{{ $invoice->student?->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-500">{{ $invoice->student?->student_number }}</span>
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-700">{{ Money::format($invoice->total_minor) }}</td>
                        <td class="px-5 py-3 tabular-nums text-emerald-700">{{ Money::format($invoice->paid_minor) }}</td>
                        <td class="px-5 py-3 tabular-nums font-medium text-slate-900">{{ Money::format($invoice->balanceMinor()) }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$invoice->status" /></td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $invoices->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
