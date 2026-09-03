@php use App\Support\Money; @endphp

<x-layouts.app title="Payments" heading="Payments">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Payments' => null]" />

    <x-ui.page-header title="Payments" description="Every payment received, newest first.">
        <x-slot:actions>
            @can('payments.record')
                <x-ui.button :href="route('payments.create')">Record payment</x-ui.button>
            @endcan
            @can('finance.report')
                <x-ui.button :href="route('exports.payments')" variant="secondary">Export CSV</x-ui.button>
            @endcan

            <x-ui.button :href="route('invoices.index')" variant="secondary">Invoices</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat label="Collected all time" :value="Money::compact($collectedMinor)" note="Across every invoice" />
        <x-ui.stat label="This month" :value="Money::compact($thisMonthMinor)" :note="now()->format('F Y')" />
    </div>

    <x-ui.card class="mt-6" :padded="false">
        @if ($payments->isEmpty())
            <x-ui.empty-state icon="◎" title="No payments yet" description="Recorded payments and receipts appear here." />
        @else
            <x-ui.table :headings="['Receipt', 'Student', 'Amount', 'Method', 'Received by', '']">
                @foreach ($payments as $payment)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3">
                            <span class="font-mono text-xs text-slate-700">{{ $payment->receipt_number }}</span>
                            <span class="block text-xs text-slate-500">{{ $payment->paid_on->format('j M Y') }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="font-medium text-slate-900">{{ $payment->student?->full_name }}</span>
                            <span class="block font-mono text-xs text-slate-500">{{ $payment->student?->student_number }}</span>
                        </td>
                        <td class="px-5 py-3 tabular-nums font-medium text-emerald-700">{{ Money::format($payment->amount_minor) }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $payment->method }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $payment->receivedBy?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button :href="route('payments.receipt', $payment)" variant="ghost" size="sm">Receipt</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $payments->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
