@php use App\Support\Money; @endphp

<x-layouts.app title="Record payment" heading="Record payment">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Payments' => route('payments.index'), 'Record' => null]" />

    <x-ui.page-header title="Record a payment" description="A receipt is generated automatically." />

    @if ($invoices->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="◫" title="Nothing outstanding" description="Every invoice has been settled." />
        </x-ui.card>
    @else
        <form method="POST" action="{{ route('payments.store') }}" class="max-w-2xl space-y-6">
            @csrf

            <x-ui.card title="Payment details">
                {{--
                    `method` is tracked here so the reference field can say when
                    it is required. The server enforces it too — this only tells
                    the bursar before they submit rather than after.
                --}}
                <div x-data="{ method: '' }" class="space-y-5">
                    <x-ui.field label="Student" name="invoice_id" required
                                hint="Type a name, a class or a student number. Every outstanding invoice is listed.">
                        {{--
                            Name *and* class, because two children called Mary
                            Doe is not an edge case, and taking money against
                            the wrong one is not a mistake the receipt reveals.
                        --}}
                        <select name="invoice_id" id="invoice_id" required
                                class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 transition hover:ring-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">
                            <option value="">Select the student's invoice</option>

                            @foreach ($invoices as $invoice)
                                <option value="{{ $invoice->id }}" @selected(old('invoice_id') == $invoice->id)>
                                    {{ $invoice->student?->full_name }}
                                    ·
                                    {{ $invoice->student?->currentEnrollment?->section?->full_name ?? 'no class' }}
                                    ·
                                    {{ $invoice->student?->student_number }}
                                    —
                                    {{ $invoice->invoice_number }},
                                    balance {{ Money::format($invoice->balanceMinor()) }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    @if ($invoices->isEmpty())
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            No invoices are outstanding. A payment is always recorded against an invoice, so raise
                            one from <a href="{{ route('fees.index') }}" class="font-medium underline underline-offset-2">Fee structures</a> first.
                        </p>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Amount" name="amount" required hint="In {{ Money::CURRENCY }}.">
                            <x-ui.input name="amount" type="number" step="0.01" min="0.01" required />
                        </x-ui.field>

                        <x-ui.field label="Payment method" name="method" required>
                            <select name="method" id="method" required x-model="method"
                                    class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 transition hover:ring-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">
                                @foreach ($methods as $m)
                                    <option value="{{ $m }}" @selected(old('method') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="Date paid" name="paid_on" required>
                            <x-ui.input name="paid_on" type="date" :value="now()->toDateString()" required />
                        </x-ui.field>

                        <x-ui.field label="Transaction reference" name="reference">
                            <x-ui.input name="reference"
                                        x-bind:required="{{ Illuminate\Support\Js::from($provedMethods) }}.includes(method)"
                                        placeholder="From the bank slip or mobile-money message" />

                            {{-- The hint changes with the method, because when
                                 it is required is exactly what a bursar needs
                                 to know before they take the money. --}}
                            <p class="mt-1 text-xs"
                               x-bind:class="{{ Illuminate\Support\Js::from($provedMethods) }}.includes(method)
                                   ? 'text-amber-700 font-medium' : 'text-slate-500'">
                                <span x-show="{{ Illuminate\Support\Js::from($provedMethods) }}.includes(method)">
                                    Required. The family should bring the bank slip or transaction message.
                                </span>
                                <span x-show="! {{ Illuminate\Support\Js::from($provedMethods) }}.includes(method)">
                                    Optional for cash — the receipt is the proof.
                                </span>
                            </p>
                        </x-ui.field>

                        <x-ui.field label="Note" name="note" class="sm:col-span-2">
                            <x-ui.textarea name="note" rows="2" />
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            <div class="flex items-center justify-end gap-2">
                <x-ui.button :href="route('payments.index')" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit">Record payment</x-ui.button>
            </div>
        </form>
    @endif
</x-layouts.app>
