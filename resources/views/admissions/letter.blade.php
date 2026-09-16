@php
    /*
     | The letter a family carries away. It is the only piece of paper that
     | says a child has a place, and it gets shown at other schools, at banks
     | and at government offices, so it is written to stand on its own: who was
     | admitted, to what, on whose authority, and how to check it.
     */
    $settings = app(\App\Services\SchoolSettings::class)->all();
@endphp

<x-layouts.app :title="'Admission letter · '.$admission->student_name" heading="Admission letter">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Admissions' => route('admissions.index'),
        $admission->student_name => route('admissions.show', $admission),
        'Letter' => null,
    ]" />

    <div class="mb-4 flex flex-wrap justify-end gap-2 print:hidden">
        <x-ui.button :href="route('admissions.show', $admission)" variant="secondary">Back to application</x-ui.button>
        <x-ui.button onclick="window.print()">Print</x-ui.button>
    </div>

    <div class="printable mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-10 shadow-sm print:max-w-none print:border-0 print:shadow-none">
        <header class="flex items-start justify-between gap-4 border-b-2 border-slate-800 pb-5">
            <div class="flex items-center gap-3">
                @if ($school?->logo_path)
                    <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt="" class="size-16 rounded-lg object-cover">
                @else
                    <span class="grid size-16 place-items-center rounded-lg bg-brand font-display text-xl font-bold text-white">
                        {{ $school?->initials() }}
                    </span>
                @endif

                <div>
                    <p class="font-display text-xl font-bold text-slate-900">{{ $school?->name }}</p>
                    @if ($school?->motto)
                        <p class="text-xs italic text-slate-500">{{ $school->motto }}</p>
                    @endif
                    <p class="mt-0.5 text-xs text-slate-500">{{ $school?->address }}</p>
                    <p class="text-xs text-slate-500">
                        {{ collect([$school?->phone, $school?->email])->filter()->implode(' · ') }}
                    </p>
                </div>
            </div>

            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Letter of admission</p>
                <p class="text-sm font-medium text-slate-900">{{ $admission->application_number }}</p>
                <p class="text-xs text-slate-500">{{ now()->format('j F Y') }}</p>
            </div>
        </header>

        <div class="space-y-5 py-7 text-sm leading-relaxed text-slate-700">
            <p class="font-medium text-slate-900">{{ $admission->guardian_name ?: 'Dear parent or guardian' }}</p>

            @if ($admission->guardian_address)
                <p class="whitespace-pre-line text-slate-500">{{ $admission->guardian_address }}</p>
            @endif

            {{--
                Built as one string rather than stitched from @if blocks: a
                letter that reads "a place at Grace Foundation Institution ."
                because the class was blank is the kind of thing a family
                notices and the school does not.
            --}}
            @php
                $offer = collect([
                    $admission->intended_class ? 'in '.$admission->intended_class : null,
                    $admission->academic_year ? 'for the '.$admission->academic_year.' academic year' : null,
                ])->filter()->implode(' ');
            @endphp

            <p>
                We are pleased to inform you that
                <span class="font-semibold text-slate-900">{{ $admission->student_name }}</span>
                has been offered a place at {{ $school?->name }}{{ $offer !== '' ? ' '.$offer : '' }}.
            </p>

            {{-- The facts anyone verifying this letter will want to line up. --}}
            <dl class="grid gap-x-6 gap-y-3 rounded-lg bg-slate-50 p-5 sm:grid-cols-2">
                @foreach ([
                    'Applicant' => $admission->student_name,
                    'Application number' => $admission->application_number,
                    'Class offered' => $admission->intended_class,
                    'Academic year' => $admission->academic_year,
                    'Date of birth' => $admission->date_of_birth?->format('j F Y'),
                    'Previous school' => $admission->previous_school,
                ] as $label => $value)
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($admission->status === 'enrolled')
                <p>
                    This place has been taken up and {{ $admission->student_first_name }} is enrolled.
                </p>
            @else
                <p>
                    To take up this place, please report to the school office with this letter and the
                    original copies of the documents submitted with the application. A place is held
                    until registration closes for the term.
                </p>
            @endif

            <p>
                We look forward to welcoming {{ $admission->student_first_name }} to the school.
            </p>
        </div>

        <div class="mt-8 flex items-end justify-between gap-6">
            <div>
                <div class="h-10 w-52 border-b border-slate-400"></div>
                <p class="mt-1 text-xs text-slate-500">Registrar, {{ $school?->name }}</p>
            </div>

            <div>
                <div class="h-10 w-52 border-b border-slate-400"></div>
                <p class="mt-1 text-xs text-slate-500">Principal</p>
            </div>
        </div>

        {{--
            The code is the point of the whole document: an admission letter is
            trivially retyped, and this is what lets the next school confirm
            the offer without telephoning ours.
        --}}
        @if (! empty($verifyCode))
            <div class="mt-7 flex items-center gap-3 border-t border-slate-200 pt-4">
                <div class="shrink-0">{!! $verifyCode !!}</div>
                <p class="text-xs leading-relaxed text-slate-500">
                    Scan to verify this letter against {{ $school?->name }}'s records.<br>
                    The check confirms the application number and its status, nothing more.
                </p>
            </div>
        @endif
    </div>
</x-layouts.app>
