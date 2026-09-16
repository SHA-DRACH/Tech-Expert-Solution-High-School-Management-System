@php
    use App\Support\Money;

    $editing = $scholarship !== null;
@endphp

<x-layouts.app :title="$editing ? 'Edit scholarship' : 'Award a scholarship'"
               :heading="$editing ? 'Edit scholarship' : 'Award a scholarship'">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Scholarships' => route('scholarships.index'),
        $editing ? 'Edit' : 'Award' => null,
    ]" />

    <x-ui.page-header :title="$editing ? 'Edit scholarship' : 'Award a scholarship'"
                      description="Applied automatically the next time fees are raised." />

    <form method="POST"
          action="{{ $editing ? route('scholarships.update', $scholarship) : route('scholarships.store') }}"
          class="max-w-2xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        {{--
            `type` drives which award field is shown and required. The server
            validates it again — this only saves the bursar from filling in a
            box that was never going to be used.
        --}}
        <div x-data="{ type: '{{ old('type', $scholarship?->type ?? 'percentage') }}' }" class="space-y-6">
            <x-ui.card title="Student">
                <x-ui.field label="Student" name="student_id" required
                            hint="Name, class and student number, so two children with the same name cannot be confused.">
                    <select name="student_id" id="student_id" required
                            class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 transition hover:ring-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">
                        <option value="">Select a student</option>

                        @foreach ($students as $student)
                            <option value="{{ $student->id }}"
                                    @selected(old('student_id', $scholarship?->student_id) == $student->id)>
                                {{ $student->full_name }}
                                ·
                                {{ $student->currentEnrollment?->section?->full_name ?? 'no class' }}
                                ·
                                {{ $student->student_number }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>
            </x-ui.card>

            <x-ui.card title="The award">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field label="Award name" name="name" required
                                    hint="What it is called — this appears on the invoice.">
                            <x-ui.input name="name" :value="$scholarship?->name" required
                                        placeholder="e.g. Principal's merit award" />
                        </x-ui.field>

                        <x-ui.field label="Sponsor" name="sponsor"
                                    hint="Who is funding it, if anyone outside the school.">
                            <x-ui.input name="sponsor" :value="$scholarship?->sponsor"
                                        placeholder="e.g. Ministry of Education" />
                        </x-ui.field>

                        <x-ui.field label="Reference" name="reference"
                                    hint="A letter or agreement number, if there is one.">
                            <x-ui.input name="reference" :value="$scholarship?->reference" />
                        </x-ui.field>

                        <x-ui.field label="Kind of award" name="type" required>
                            <select name="type" id="type" required x-model="type"
                                    class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 transition hover:ring-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">
                                <option value="percentage">A percentage of the fees</option>
                                <option value="amount">A fixed amount off the fees</option>
                            </select>
                        </x-ui.field>
                    </div>

                    {{-- Only one of these is ever in play. --}}
                    <div x-show="type === 'percentage'" x-cloak>
                        <x-ui.field label="Percentage covered" name="percentage"
                                    hint="100% is a full waiver. Recalculated whenever fees change.">
                            <x-ui.input name="percentage" type="number" step="0.01" min="0.01" max="100"
                                        :value="$scholarship?->percentage" placeholder="e.g. 50" />
                        </x-ui.field>
                    </div>

                    <div x-show="type === 'amount'" x-cloak>
                        <x-ui.field label="Amount covered" name="amount"
                                    :hint="'In '.Money::CURRENCY.'. Never more than the bill itself.'">
                            <x-ui.input name="amount" type="number" step="0.01" min="0.01"
                                        :value="$scholarship && $scholarship->amount_minor !== null
                                            ? Money::toMajor($scholarship->amount_minor)
                                            : null"
                                        placeholder="e.g. 5000" />
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="When it applies"
                       description="Leave the year and term blank for an award that stands until it is ended.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Academic year" name="academic_year_id">
                        <x-ui.select name="academic_year_id"
                                     :selected="old('academic_year_id', $scholarship?->academic_year_id)"
                                     placeholder="Every year"
                                     :options="$years->pluck('name', 'id')->all()" />
                    </x-ui.field>

                    <x-ui.field label="Term" name="term_id">
                        <x-ui.select name="term_id"
                                     :selected="old('term_id', $scholarship?->term_id)"
                                     placeholder="All terms"
                                     :options="$terms->mapWithKeys(fn ($term) => [
                                         $term->id => $term->name.' · '.($term->academicYear?->name ?? ''),
                                     ])->all()" />
                    </x-ui.field>

                    <x-ui.field label="Starts on" name="starts_on">
                        <x-ui.input name="starts_on" type="date"
                                    :value="$scholarship?->starts_on?->toDateString()" />
                    </x-ui.field>

                    <x-ui.field label="Ends on" name="ends_on" hint="Leave blank for an open-ended award.">
                        <x-ui.input name="ends_on" type="date"
                                    :value="$scholarship?->ends_on?->toDateString()" />
                    </x-ui.field>

                    <x-ui.field label="Status" name="status" required
                                hint="Suspended keeps the record but stops it discounting new fees.">
                        <x-ui.select name="status" :selected="old('status', $scholarship?->status ?? 'active')"
                                     :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
                    </x-ui.field>

                    <x-ui.field label="Notes" name="notes" class="sm:col-span-2">
                        <x-ui.textarea name="notes" rows="3" :value="$scholarship?->notes"
                                       placeholder="Conditions, review dates, anything the next bursar will need." />
                    </x-ui.field>
                </div>
            </x-ui.card>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('scholarships.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">{{ $editing ? 'Save changes' : 'Award scholarship' }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
