@php use App\Support\Money; @endphp

<x-layouts.app title="Fee structures" heading="Fee structures">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Fee structures' => null]" />

    <x-ui.page-header
        title="Fee structures"
        description="The price list for a class and term. Raising invoices from one is a separate step."
    >
        <x-slot:actions>
            <x-ui.button :href="route('invoices.index')" variant="secondary">Invoices</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @forelse ($structures as $structure)
                <x-ui.card
                    :title="$structure->name"
                    :description="($structure->schoolClass?->name ?? 'All classes').' · '.($structure->term?->name ?? 'Whole year')"
                    data-aos="fade-up"
                >
                    <x-slot:actions>
                        <span class="font-display text-base font-bold tabular-nums text-slate-900">
                            {{ Money::format($structure->totalMinor()) }}
                        </span>
                    </x-slot:actions>

                    {{-- Editing posts the whole list back, so the form mirrors it exactly. --}}
                    <form method="POST" action="{{ route('fees.update', $structure) }}" enctype="multipart/form-data"
                          x-data="{ items: {{ Js::from($structure->items->map(fn ($i) => [
                              'category' => $i->category,
                              'description' => $i->description,
                              'amount' => number_format($i->amount_minor / 100, 2, '.', ''),
                          ])->values()) }} }">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Name" name="name" required>
                                <x-ui.input name="name" :value="$structure->name" required />
                            </x-ui.field>

                            <x-ui.field label="Description" name="description">
                                <x-ui.input name="description" :value="$structure->description" />
                            </x-ui.field>
                        </div>

                        <div class="mt-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee lines</p>

                            <div class="space-y-2">
                                <template x-for="(item, index) in items" :key="index">
                                    <div class="grid grid-cols-[9rem_1fr_7rem_2rem] items-center gap-2">
                                        <select :name="`items[${index}][category]`" x-model="item.category" required
                                                class="rounded-lg border-0 py-1.5 pl-2 pr-7 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                            @foreach ($categories as $category)
                                                <option value="{{ $category }}">{{ $category }}</option>
                                            @endforeach
                                        </select>

                                        <input type="text" :name="`items[${index}][description]`" x-model="item.description"
                                               maxlength="180" placeholder="Optional description"
                                               class="rounded-lg border-0 px-2 py-1.5 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">

                                        <input type="number" :name="`items[${index}][amount]`" x-model="item.amount"
                                               step="0.01" min="0" required
                                               class="rounded-lg border-0 px-2 py-1.5 text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">

                                        <button type="button" @click="items.splice(index, 1)"
                                                class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                aria-label="Remove fee line">&times;</button>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2">
                                <x-ui.button type="button" variant="ghost" size="sm"
                                             @click="items.push({ category: 'Tuition', description: '', amount: '0.00' })">
                                    Add a line
                                </x-ui.button>

                                <span class="text-sm text-slate-600">
                                    Total
                                    <strong class="ml-1 font-display tabular-nums text-slate-900"
                                            x-text="'{{ Money::CURRENCY }} ' + items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                                </span>
                            </div>
                        </div>

                        {{-- The printed fee schedule, for families to download. --}}
                        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee document (PDF)</p>

                            @if ($structure->hasDocument())
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
                                    <a href="{{ route('fees.document.download', $structure) }}" class="font-medium text-brand hover:underline">
                                        📄 {{ $structure->document_name }}
                                    </a>
                                    <label class="flex items-center gap-1.5 text-xs text-rose-600">
                                        <input type="checkbox" name="remove_document" value="1" class="size-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                        Remove
                                    </label>
                                </div>
                            @endif

                            <label for="document-{{ $structure->id }}" class="sr-only">Upload a PDF</label>
                            <input type="file" id="document-{{ $structure->id }}" name="document" accept="application/pdf,.pdf"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 file:ring-1 file:ring-slate-200 hover:file:bg-slate-100">
                            <p class="mt-1 text-xs text-slate-500">{{ $structure->hasDocument() ? 'Choose a file to replace it.' : 'Optional.' }} PDF, up to 10 MB. Students and parents it applies to can download it.</p>

                            <label class="mt-3 flex items-start gap-2">
                                <input type="checkbox" name="document_on_website" value="1" @checked($structure->document_on_website)
                                       class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                <span class="text-sm text-slate-600">Also show it on the public <strong>Online services</strong> page, for anyone to download</span>
                            </label>
                            @error('document')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" @checked($structure->is_active)
                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                <span class="text-sm text-slate-600">Active</span>
                            </label>

                            <div class="flex items-center gap-2">
                                <x-ui.confirm
                                    :action="route('fees.destroy', $structure)"
                                    method="DELETE"
                                    title="Delete this fee structure?"
                                    :message="'“'.$structure->name.'” will be removed. Invoices already raised from it are not affected.'"
                                    confirm="Delete structure"
                                    class="text-rose-600 hover:bg-rose-50"
                                >Delete</x-ui.confirm>

                                <x-ui.button type="submit">Save changes</x-ui.button>
                            </div>
                        </div>
                    </form>

                    @can('invoices.manage')
                        <form method="POST" action="{{ route('fees.raise') }}"
                              class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50 p-4">
                            @csrf
                            <input type="hidden" name="fee_structure_id" value="{{ $structure->id }}">

                            <div class="min-w-40">
                                <label for="due-{{ $structure->id }}" class="block text-sm font-medium text-slate-700">
                                    Payment due by
                                </label>
                                <input type="date" name="due_on" id="due-{{ $structure->id }}"
                                       class="mt-1.5 block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                            </div>

                            <x-ui.button type="submit" variant="secondary">
                                Raise invoices for {{ $structure->schoolClass?->name ?? 'every student' }}
                            </x-ui.button>

                            <p class="w-full text-xs text-slate-500">
                                Students who already have an invoice for this period are skipped, so this is safe to run twice.
                            </p>
                        </form>
                    @endcan
                </x-ui.card>
            @empty
                <x-ui.card>
                    <x-ui.empty-state
                        icon="◫"
                        title="No fee structures yet"
                        description="Build a price list for a class, then raise invoices from it."
                    />
                </x-ui.card>
            @endforelse
        </div>

        <x-ui.card title="New fee structure">
            <form method="POST" action="{{ route('fees.store') }}" class="space-y-4" enctype="multipart/form-data"
                  x-data="{ items: [{ category: 'Tuition', description: '', amount: '' }] }">
                @csrf

                <x-ui.field label="Name" name="name" required>
                    <x-ui.input name="name" required placeholder="Grade 7 — First Term" />
                </x-ui.field>

                <x-ui.field label="Class" name="school_class_id" hint="Leave blank to apply to every student.">
                    <x-ui.select name="school_class_id" placeholder="All classes"
                                 :options="$classes->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Term" name="term_id" hint="Leave blank for a whole-year charge.">
                    <x-ui.select name="term_id" placeholder="Whole year"
                                 :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
                </x-ui.field>

                <div>
                    <p class="mb-2 text-sm font-medium text-slate-700">Fee lines</p>

                    @error('items')
                        <p class="mb-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror

                    <div class="space-y-2">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid grid-cols-[1fr_6rem_2rem] items-center gap-2">
                                <select :name="`items[${index}][category]`" x-model="item.category" required
                                        class="rounded-lg border-0 py-1.5 pl-2 pr-7 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>

                                <input type="number" :name="`items[${index}][amount]`" x-model="item.amount"
                                       step="0.01" min="0" required placeholder="0.00"
                                       class="rounded-lg border-0 px-2 py-1.5 text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand">

                                <button type="button" @click="items.splice(index, 1)" x-show="items.length > 1"
                                        class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                        aria-label="Remove fee line">&times;</button>
                            </div>
                        </template>
                    </div>

                    <x-ui.button type="button" variant="ghost" size="sm" class="mt-2"
                                 @click="items.push({ category: 'Tuition', description: '', amount: '' })">
                        Add a line
                    </x-ui.button>
                </div>

                <x-ui.field label="Fee document (PDF)" name="document" hint="Optional. The printed fee schedule, up to 10 MB, for students and parents to download.">
                    <input type="file" id="document" name="document" accept="application/pdf,.pdf"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>

                <label class="flex items-start gap-2">
                    <input type="checkbox" name="document_on_website" value="1" @checked(old('document_on_website'))
                           class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand">
                    <span class="text-sm text-slate-600">Also show the PDF on the public <strong>Online services</strong> page</span>
                </label>

                <x-ui.button type="submit" class="w-full">Create structure</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
