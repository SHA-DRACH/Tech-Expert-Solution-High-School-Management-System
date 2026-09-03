@php
    use App\Support\Money;

@endphp
<x-layouts.app title="Fees">
    <x-ui.page-header title="Fees" :description="$child->full_name" />

    <x-portal.child-selector :children="$children" :selected="$child" route="parent.fees" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Total invoiced" :value="Money::format($totalMinor)" note="This academic year" />
        <x-ui.stat label="Amount paid" :value="Money::format($paidMinor)" note="Received by the school" />
        <x-ui.stat label="Outstanding" :value="Money::format($outstandingMinor)" note="Still to be paid" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Invoices" :padded="false">
            @forelse ($invoices as $invoice)
                <div class="border-b border-slate-100 px-5 py-4 last:border-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-slate-500">{{ $invoice->invoice_number }}</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-900">{{ Money::format($invoice->total_minor) }}</p>
                            <p class="text-xs text-slate-500">
                                Issued {{ $invoice->issued_on->format('j M Y') }}
                                @if ($invoice->due_on) · due {{ $invoice->due_on->format('j M Y') }} @endif
                            </p>
                        </div>
                        <x-ui.status-badge :status="$invoice->status" />
                    </div>

                    @if ($invoice->items->isNotEmpty())
                        <dl class="mt-3 space-y-1 rounded-lg bg-slate-50 p-3 text-xs">
                            @foreach ($invoice->items as $item)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-600">{{ $item->category }}</dt>
                                    <dd class="tabular-nums text-slate-700">{{ Money::format($item->amount_minor) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($invoice->balanceMinor() > 0)
                        <p class="mt-2 text-xs font-medium text-amber-700">
                            Balance {{ Money::format($invoice->balanceMinor()) }}
                        </p>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="◫" title="No invoices" description="Fee invoices will appear here once raised." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Payment history" :padded="false">
            @forelse ($payments as $payment)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div>
                        <p class="font-mono text-xs text-slate-500">{{ $payment->receipt_number }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $payment->paid_on->format('j M Y') }} · {{ $payment->method }}
                        </p>
                    </div>
                    <span class="font-display text-sm font-bold tabular-nums text-emerald-700">
                        {{ Money::format($payment->amount_minor) }}
                    </span>
                </div>
            @empty
                <x-ui.empty-state icon="◎" title="No payments yet" description="Receipts appear here after each payment." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
