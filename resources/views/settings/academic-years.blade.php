@php
    $current = $years->firstWhere('is_current', true);
@endphp

<x-layouts.app title="Academic years & terms" heading="Academic years & terms">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Settings' => route('settings.index'),
        'Academic years & terms' => null,
    ]" />

    <x-ui.page-header
        title="Academic years & terms"
        description="Enrolment, attendance, marks, report cards and invoices are all filed against a year and a term."
    />

    @error('year')
        <div class="enter-rise mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror
    @error('term')
        <div class="enter-rise mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-5">
            @forelse ($years as $year)
                @php $terms = $year->terms; @endphp

                <x-ui.card x-data="{ editing: false, adding: false }" :padded="false">
                    <x-slot:title>
                        <span class="flex flex-wrap items-center gap-2">
                            {{ $year->name }}
                            @if ($year->is_current)
                                <x-ui.badge tone="success">Current year</x-ui.badge>
                            @endif
                        </span>
                    </x-slot:title>

                    <x-slot:description>
                        {{ $year->starts_on->format('j M Y') }} &ndash; {{ $year->ends_on->format('j M Y') }}
                        · {{ $year->terms_count }} {{ Str::plural('term', $year->terms_count) }}
                        · {{ $year->enrollments_count }} {{ Str::plural('enrolment', $year->enrollments_count) }}
                    </x-slot:description>

                    <x-slot:actions>
                        @unless ($year->is_current)
                            <form method="POST" action="{{ route('settings.years.current', $year) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">Make current</x-ui.button>
                            </form>
                        @endunless

                        <x-ui.button size="sm" variant="ghost" x-on:click="editing = ! editing">Edit</x-ui.button>
                    </x-slot:actions>

                    {{-- Editing the year itself --}}
                    <div x-show="editing" x-cloak x-collapse class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <form method="POST" action="{{ route('settings.years.update', $year) }}"
                              class="grid gap-4 sm:grid-cols-3">
                            @csrf
                            @method('PUT')

                            <x-ui.field label="Name" name="name" :id="'year-name-'.$year->id">
                                <x-ui.input name="name" :id="'year-name-'.$year->id" :value="$year->name" :remember="false" />
                            </x-ui.field>

                            <x-ui.field label="Starts on" name="starts_on" :id="'year-start-'.$year->id">
                                <x-ui.input name="starts_on" type="date" :id="'year-start-'.$year->id" :value="$year->starts_on->toDateString()" :remember="false" />
                            </x-ui.field>

                            <x-ui.field label="Ends on" name="ends_on" :id="'year-end-'.$year->id">
                                <x-ui.input name="ends_on" type="date" :id="'year-end-'.$year->id" :value="$year->ends_on->toDateString()" :remember="false" />
                            </x-ui.field>

                            <div class="flex items-center gap-2 sm:col-span-3">
                                <x-ui.button type="submit" size="sm">Save year</x-ui.button>

                                @unless ($year->is_current)
                                    <x-ui.confirm
                                        :action="route('settings.years.destroy', $year)"
                                        method="DELETE"
                                        title="Delete academic year"
                                        message="Delete {{ $year->name }}? This is only possible while the year holds no records."
                                        confirm="Delete year"
                                        >Delete year</x-ui.confirm>
                                @endunless
                            </div>
                        </form>
                    </div>

                    {{-- The terms --}}
                    @if ($terms->isEmpty())
                        <div class="px-5 py-6 text-center">
                            <p class="text-sm text-slate-500">
                                This year has no terms yet. A term is what attendance and marks are recorded against.
                            </p>
                        </div>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($terms as $term)
                                <li x-data="{ open: false }">
                                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                                        <div class="min-w-0">
                                            <p class="flex items-center gap-2 text-sm font-medium text-slate-800">
                                                <span class="grid size-6 shrink-0 place-items-center rounded-md bg-slate-100 text-xs font-semibold text-slate-500">
                                                    {{ $term->sequence }}
                                                </span>
                                                {{ $term->name }}
                                                @if ($term->is_current)
                                                    <x-ui.badge tone="success">Current term</x-ui.badge>
                                                @endif
                                            </p>
                                            <p class="mt-0.5 pl-8 text-xs text-slate-500">
                                                {{ $term->starts_on->format('j M Y') }} &ndash; {{ $term->ends_on->format('j M Y') }}
                                            </p>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-2">
                                            @if (! $term->is_current && $year->is_current)
                                                <form method="POST" action="{{ route('settings.terms.current', $term) }}">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="secondary" size="sm">Make current</x-ui.button>
                                                </form>
                                            @endif

                                            <x-ui.button size="sm" variant="ghost" x-on:click="open = ! open">Edit</x-ui.button>
                                        </div>
                                    </div>

                                    <div x-show="open" x-cloak x-collapse class="bg-slate-50 px-5 py-4">
                                        <form method="POST" action="{{ route('settings.terms.update', $term) }}"
                                              class="grid gap-4 sm:grid-cols-4">
                                            @csrf
                                            @method('PUT')

                                            <x-ui.field label="Name" name="name" :id="'term-name-'.$term->id">
                                                <x-ui.input name="name" :id="'term-name-'.$term->id" :value="$term->name" :remember="false" />
                                            </x-ui.field>

                                            <x-ui.field label="Order" name="sequence" :id="'term-seq-'.$term->id">
                                                <x-ui.input name="sequence" type="number" min="1" max="6" :id="'term-seq-'.$term->id" :value="$term->sequence" :remember="false" />
                                            </x-ui.field>

                                            <x-ui.field label="Starts on" name="starts_on" :id="'term-start-'.$term->id">
                                                <x-ui.input name="starts_on" type="date" :id="'term-start-'.$term->id" :value="$term->starts_on->toDateString()" :remember="false" />
                                            </x-ui.field>

                                            <x-ui.field label="Ends on" name="ends_on" :id="'term-end-'.$term->id">
                                                <x-ui.input name="ends_on" type="date" :id="'term-end-'.$term->id" :value="$term->ends_on->toDateString()" :remember="false" />
                                            </x-ui.field>

                                            <div class="flex items-center gap-2 sm:col-span-4">
                                                <x-ui.button type="submit" size="sm">Save term</x-ui.button>

                                                @unless ($term->is_current)
                                                    <x-ui.confirm
                                                        :action="route('settings.terms.destroy', $term)"
                                                        method="DELETE"
                                                        title="Delete term"
                                                        message="Delete {{ $term->name }}? This is only possible while the term holds no records."
                                                        confirm="Delete term"
                                                        >Delete term</x-ui.confirm>
                                                @endunless
                                            </div>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Adding a term to this year --}}
                    <div class="border-t border-slate-100 px-5 py-3">
                        <x-ui.button size="sm" variant="ghost" x-on:click="adding = ! adding">
                            <span aria-hidden="true">+</span> Add a term to {{ $year->name }}
                        </x-ui.button>

                        <div x-show="adding" x-cloak x-collapse class="pt-4">
                            <form method="POST" action="{{ route('settings.terms.store', $year) }}"
                                  class="grid gap-4 sm:grid-cols-4">
                                @csrf

                                <x-ui.field label="Name" name="name" :id="'new-term-name-'.$year->id">
                                    <x-ui.input name="name" :id="'new-term-name-'.$year->id" placeholder="Second Term" :remember="false" />
                                </x-ui.field>

                                <x-ui.field label="Order" name="sequence" :id="'new-term-seq-'.$year->id">
                                    <x-ui.input name="sequence" type="number" min="1" max="6" :id="'new-term-seq-'.$year->id" :value="(int) $terms->max('sequence') + 1" :remember="false" />
                                </x-ui.field>

                                <x-ui.field label="Starts on" name="starts_on" :id="'new-term-start-'.$year->id">
                                    <x-ui.input name="starts_on" type="date" :id="'new-term-start-'.$year->id" :remember="false" />
                                </x-ui.field>

                                <x-ui.field label="Ends on" name="ends_on" :id="'new-term-end-'.$year->id">
                                    <x-ui.input name="ends_on" type="date" :id="'new-term-end-'.$year->id" :remember="false" />
                                </x-ui.field>

                                <div class="sm:col-span-4">
                                    <x-ui.button type="submit" size="sm">Add term</x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty-state
                    title="No academic year yet"
                    description="Start by creating the year the school is teaching. Everything else is filed against it."
                />
            @endforelse
        </div>

        {{-- Creating a year --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <x-ui.card title="Open a new academic year"
                       description="Years may not overlap: a date has to belong to exactly one of them.">
                <form method="POST" action="{{ route('settings.years.store') }}" class="space-y-4">
                    @csrf

                    <x-ui.field label="Name" name="name" required hint="However your school writes it, such as 2026 / 2027.">
                        <x-ui.input name="name" placeholder="2026 / 2027" required />
                    </x-ui.field>

                    <x-ui.field label="Starts on" name="starts_on" required>
                        <x-ui.input name="starts_on" type="date" required />
                    </x-ui.field>

                    <x-ui.field label="Ends on" name="ends_on" required>
                        <x-ui.input name="ends_on" type="date" required />
                    </x-ui.field>

                    <x-ui.button type="submit" class="w-full">Create year</x-ui.button>
                </form>
            </x-ui.card>

            @if ($current)
                <x-ui.card class="mt-5" title="Where the school is now">
                    <p class="text-sm text-slate-600">
                        <span class="font-medium text-slate-900">{{ $current->name }}</span>
                        @php $currentTerm = $current->terms->firstWhere('is_current', true); @endphp
                        @if ($currentTerm)
                            &middot; {{ $currentTerm->name }}
                        @else
                            <span class="text-amber-700">&middot; no current term set</span>
                        @endif
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        New attendance, marks and invoices are filed here until you move the school on.
                    </p>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
