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
                <div class="space-y-5">
                    <x-ui.field label="Invoice" name="invoice_id" required>
                        <x-ui.select name="invoice_id" required placeholder="Select an outstanding invoice"
                                     :options="$invoices->mapWithKeys(fn ($i) => [
                                        $i->id => $i->invoice_number.' — '.$i->student?->full_name.' — balance '.Money::format($i->balanceMinor()),
                                     ])->all()" />
                    </x-ui.field>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Amount" name="amount" required hint="In {{ Money::CURRENCY }}.">
                            <x-ui.input name="amount" type="number" step="0.01" min="0.01" required />
                        </x-ui.field>

                        <x-ui.field label="Payment method" name="method" required>
                            <x-ui.select name="method" required
                                         :options="collect($methods)->mapWithKeys(fn ($m) => [$m => $m])->all()" />
                        </x-ui.field>

                        <x-ui.field label="Date paid" name="paid_on" required>
                            <x-ui.input name="paid_on" type="date" :value="now()->toDateString()" required />
                        </x-ui.field>

                        <x-ui.field label="Reference" name="reference" hint="Transaction or cheque number.">
                            <x-ui.input name="reference" />
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
