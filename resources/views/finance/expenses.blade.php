@php use App\Support\Money; @endphp

<x-layouts.app title="Expenses" heading="Expenses">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Expenses' => null]" />

    <x-ui.page-header title="Expenses" description="What the school spends, set against what it collects.">
        <x-slot:actions>
            <x-ui.button :href="route('payments.index')" variant="secondary">Payments</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Fees collected" :value="Money::compact($collectedMinor)" note="All payments received" />
        <x-ui.stat label="Total spent" :value="Money::compact($spentMinor)" note="Matching the filters below" />
        <x-ui.stat
            label="Balance"
            :value="Money::compact($balanceMinor)"
            :note="$balanceMinor < 0 ? 'Spending exceeds collection' : 'Collected minus spent'"
        />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :padded="false">
                <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="w-48">
                        <label for="category" class="sr-only">Category</label>
                        <x-ui.select name="category" :selected="$filters['category']" placeholder="All categories"
                                     :options="collect($categories)->mapWithKeys(fn ($c) => [$c => $c])->all()" />
                    </div>

                    <div class="w-40">
                        <label for="from" class="sr-only">From</label>
                        <x-ui.input name="from" type="date" :value="$filters['from']?->toDateString()" />
                    </div>

                    <div class="w-40">
                        <label for="to" class="sr-only">To</label>
                        <x-ui.input name="to" type="date" :value="$filters['to']?->toDateString()" />
                    </div>

                    <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

                    @if (array_filter($filters))
                        <x-ui.button :href="route('expenses.index')" variant="ghost">Clear</x-ui.button>
                    @endif
                </form>

                @if ($expenses->isEmpty())
                    <x-ui.empty-state
                        icon="◫"
                        title="No expenses recorded"
                        description="Record what the school spends to see the full financial picture."
                    />
                @else
                    <x-ui.table :headings="['Date', 'Category', 'Description', 'Amount', 'Recorded by', '']">
                        @foreach ($expenses as $expense)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                                    {{ $expense->spent_on->format('j M Y') }}
                                </td>
                                <td class="px-5 py-3"><x-ui.badge>{{ $expense->category }}</x-ui.badge></td>
                                <td class="px-5 py-3 text-slate-700">
                                    {{ $expense->description }}
                                    @if ($expense->reference)
                                        <span class="block font-mono text-xs text-slate-400">{{ $expense->reference }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 tabular-nums font-medium text-slate-900">
                                    {{ Money::format($expense->amount_minor) }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $expense->recordedBy?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div x-data="{ editing: false }" class="inline-flex items-center gap-1">
                                        <x-ui.button size="sm" variant="ghost" x-on:click="editing = true">Edit</x-ui.button>

                                        <x-ui.confirm
                                            :action="route('expenses.destroy', $expense)"
                                            method="DELETE"
                                            title="Remove this expense?"
                                            :message="'“'.$expense->description.'” of '.Money::format($expense->amount_minor).' will be removed from the records.'"
                                            confirm="Remove expense"
                                            class="text-rose-600 hover:bg-rose-50"
                                        >Remove</x-ui.confirm>

                                        {{-- Teleported out of the table: a form nested in a
                                             <tr> is invalid markup and the browser hoists it
                                             out, which detaches the fields from it. --}}
                                        <template x-teleport="body">
                                            <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                                 role="dialog" aria-modal="true" @keydown.escape.window="editing = false">
                                                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="editing = false"></div>

                                                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                                                    <h2 class="text-base font-semibold text-slate-900">Edit expense</h2>
                                                    <p class="mt-1 text-sm text-slate-600">
                                                        The old and new amount are both recorded in the audit trail.
                                                    </p>

                                                    <form method="POST" action="{{ route('expenses.update', $expense) }}"
                                                          class="mt-5 grid gap-4 sm:grid-cols-2">
                                                        @csrf
                                                        @method('PUT')

                                                        <x-ui.field label="Category" name="category" :id="'ex-cat-'.$expense->id">
                                                            <x-ui.input name="category" :id="'ex-cat-'.$expense->id"
                                                                        :value="$expense->category" :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Date" name="spent_on" :id="'ex-date-'.$expense->id">
                                                            <x-ui.input name="spent_on" type="date" :id="'ex-date-'.$expense->id"
                                                                        :value="$expense->spent_on?->toDateString()" :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Description" name="description" :id="'ex-desc-'.$expense->id" class="sm:col-span-2">
                                                            <x-ui.input name="description" :id="'ex-desc-'.$expense->id"
                                                                        :value="$expense->description" :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Amount" name="amount" :id="'ex-amt-'.$expense->id">
                                                            <x-ui.input name="amount" type="number" step="0.01" min="0.01"
                                                                        :id="'ex-amt-'.$expense->id"
                                                                        :value="number_format($expense->amount_minor / 100, 2, '.', '')"
                                                                        :remember="false" />
                                                        </x-ui.field>

                                                        <x-ui.field label="Reference" name="reference" :id="'ex-ref-'.$expense->id">
                                                            <x-ui.input name="reference" :id="'ex-ref-'.$expense->id"
                                                                        :value="$expense->reference" :remember="false" />
                                                        </x-ui.field>

                                                        <div class="flex justify-end gap-2 sm:col-span-2">
                                                            <x-ui.button variant="secondary" size="sm" @click="editing = false">Cancel</x-ui.button>
                                                            <x-ui.button type="submit" size="sm">Save expense</x-ui.button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>

                    <div class="border-t border-slate-100 px-5 py-3">{{ $expenses->links() }}</div>
                @endif
            </x-ui.card>

            @if ($byCategory->isNotEmpty())
                <x-ui.card title="Spending by category">
                    @php $largest = max($byCategory->values()->all()) ?: 1; @endphp

                    <div class="space-y-4">
                        @foreach ($byCategory as $category => $total)
                            <x-ui.progress
                                :value="round($total / $largest * 100, 1)"
                                :label="$category"
                                :caption="Money::format($total)"
                            />
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>

        <x-ui.card title="Record an expense">
            <form method="POST" action="{{ route('expenses.store') }}" class="space-y-4">
                @csrf

                <x-ui.field label="Category" name="category" required>
                    <x-ui.select name="category" required
                                 :options="collect($categories)->mapWithKeys(fn ($c) => [$c => $c])->all()" />
                </x-ui.field>

                <x-ui.field label="Description" name="description" required>
                    <x-ui.input name="description" required placeholder="Term supply of exercise books" />
                </x-ui.field>

                <x-ui.field label="Amount" name="amount" required :hint="'In '.Money::CURRENCY.'.'">
                    <x-ui.input name="amount" type="number" step="0.01" min="0.01" required />
                </x-ui.field>

                <x-ui.field label="Date spent" name="spent_on" required>
                    <x-ui.input name="spent_on" type="date" :value="now()->toDateString()" required />
                </x-ui.field>

                <x-ui.field label="Reference" name="reference" hint="Receipt or voucher number.">
                    <x-ui.input name="reference" />
                </x-ui.field>

                <x-ui.button type="submit" class="w-full">Record expense</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
