@php use App\Support\Money; @endphp

<x-layouts.app title="Receipt" heading="Receipt">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Payments' => route('payments.index'), $payment->receipt_number => null]" />

    <div class="mb-4 flex justify-end gap-2 print:hidden">
        <x-ui.button :href="route('payments.index')" variant="secondary">Back to payments</x-ui.button>
        <x-ui.button onclick="window.print()">Print receipt</x-ui.button>
    </div>

    {{-- Kept deliberately plain so it prints cleanly on any printer. --}}
    <div class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-8 shadow-sm print:border-0 print:shadow-none">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-6">
            <div class="flex items-center gap-3">
                @if ($school?->logo_path)
                    <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt="" class="size-14 rounded-lg object-cover">
                @else
                    <span class="grid size-14 place-items-center rounded-lg bg-brand font-display text-lg font-bold text-white">
                        {{ $school?->initials() }}
                    </span>
                @endif

                <div>
                    <p class="font-display text-lg font-bold text-slate-900">{{ $school?->name }}</p>
                    <p class="text-xs text-slate-500">{{ $school?->address }}</p>
                    <p class="text-xs text-slate-500">{{ $school?->phone }}{{ $school?->email ? ' · '.$school->email : '' }}</p>
                </div>
            </div>

            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Receipt</p>
                <p class="font-mono text-sm font-bold text-slate-900">{{ $payment->receipt_number }}</p>
                <p class="text-xs text-slate-500">{{ $payment->paid_on->format('j F Y') }}</p>
            </div>
        </div>

        <dl class="grid gap-x-6 gap-y-4 py-6 sm:grid-cols-2">
            @foreach ([
                'Student' => $payment->student?->full_name,
                'Student number' => $payment->student?->student_number,
                'Parent / guardian' => $payment->guardian?->full_name,
                'Invoice' => $payment->invoice?->invoice_number,
                'Payment method' => $payment->method,
                'Reference' => $payment->reference,
            ] as $label => $value)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-900">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="rounded-lg bg-slate-50 p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-700">Amount received</span>
                <span class="font-display text-2xl font-bold tabular-nums text-slate-900">
                    {{ Money::format($payment->amount_minor) }}
                </span>
            </div>

            @if ($payment->invoice)
                <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 text-sm">
                    <span class="text-slate-600">Remaining balance</span>
                    <span class="tabular-nums font-medium text-slate-900">
                        {{ Money::format($payment->invoice->balanceMinor()) }}
                    </span>
                </div>
            @endif
        </div>

        @if ($payment->note)
            <p class="mt-4 text-sm text-slate-600">{{ $payment->note }}</p>
        @endif

        <div class="mt-8 flex items-end justify-between gap-6 border-t border-slate-200 pt-6">
            <div>
                <p class="text-xs text-slate-500">Received by</p>
                <p class="mt-1 text-sm font-medium text-slate-900">{{ $payment->receivedBy?->name ?? '—' }}</p>
            </div>

            <div class="text-right">
                <div class="h-10 w-40 border-b border-slate-300"></div>
                <p class="mt-1 text-xs text-slate-500">Authorised signature</p>
            </div>
        </div>

        {{-- Whatever the school wants on every receipt: bank details, a thank-you. --}}
        @php $receiptFooter = app(\App\Services\SchoolSettings::class)->get('finance_receipt_footer'); @endphp

        @if (filled($receiptFooter))
            <p class="mt-6 whitespace-pre-line border-t border-slate-200 pt-4 text-xs text-slate-500">{{ $receiptFooter }}</p>
        @endif
    </div>
</x-layouts.app>
