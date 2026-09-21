@php use App\Support\Money; @endphp

<x-layouts.app title="My fees">
    <x-ui.page-header
        title="Fees"
        description="What has been invoiced to you, what has been paid, and what is left."
    />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Invoiced" :value="Money::format($totalMinor)" note="This academic year" />
        <x-ui.stat label="Paid" :value="Money::format($paidMinor)" note="Payments received" />
        <x-ui.stat label="Outstanding" :value="Money::format($outstandingMinor)" note="Still to be paid" />
    </div>

    @if ($feeDocuments->isNotEmpty())
        <x-ui.card class="mt-6" title="Fee structure documents" description="The school's fee schedules. Download and keep a copy.">
            @include('partials.fee-documents', ['documents' => $feeDocuments])
        </x-ui.card>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Invoices" :padded="false">
            @forelse ($invoices as $invoice)
                <div class="border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ $invoice->invoice_number }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Issued {{ $invoice->issued_on?->format('j M Y') }}
                                @if ($invoice->due_on) · due {{ $invoice->due_on->format('j M Y') }} @endif
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="font-medium tabular-nums text-slate-900">{{ Money::format($invoice->total_minor) }}</p>
                            <x-ui.status-badge :status="$invoice->status" />
                        </div>
                    </div>

                    @if ($invoice->items->isNotEmpty())
                        <ul class="mt-2 space-y-0.5 text-xs text-slate-500">
                            @foreach ($invoice->items as $item)
                                <li class="flex justify-between gap-3">
                                    <span>{{ $item->title ?? $item->category }}</span>
                                    <span class="tabular-nums">{{ Money::format($item->amount_minor) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="◫" title="No invoices" description="Nothing has been billed to you yet." />
            @endforelse
        </x-ui.card>

        <x-ui.card title="Payments received" :padded="false">
            @forelse ($payments as $payment)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">{{ Money::format($payment->amount_minor) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $payment->paid_on?->format('j M Y') }}
                            @if ($payment->method) · {{ Str::headline($payment->method) }} @endif
                        </p>
                    </div>

                    @if ($payment->reference)
                        <span class="font-mono text-xs text-slate-400">{{ $payment->reference }}</span>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="◎" title="No payments recorded" />
            @endforelse
        </x-ui.card>
    </div>

    <p class="mt-6 text-xs text-slate-500">
        Payments are recorded by the school office. If something here looks wrong, speak to them rather than
        to your teacher.
    </p>
</x-layouts.app>
