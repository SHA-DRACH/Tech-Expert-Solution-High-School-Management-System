@php
    /*
     | The sheet a registrar hands across the counter. Everything on it is
     | already somewhere in the system; the point of the page is that it is all
     | on one piece of paper, in the order it gets asked for.
     */
    $current = $student->enrollments->firstWhere('academic_year_id', $year?->id);
@endphp

<x-layouts.app :title="'Student record · '.$student->full_name" heading="Student record">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Students' => route('students.index'),
        $student->full_name => route('students.show', $student),
        'Record' => null,
    ]" />

    <div class="mb-4 flex flex-wrap justify-end gap-2 print:hidden">
        <x-ui.button :href="route('students.show', $student)" variant="secondary">Back to student</x-ui.button>
        <x-ui.button onclick="window.print()">Print</x-ui.button>
    </div>

    <div class="printable mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-8 shadow-sm print:max-w-none print:border-0 print:shadow-none">
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
                </div>
            </div>

            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Student record</p>
                <p class="font-mono text-sm font-medium text-slate-900">{{ $student->student_number }}</p>
                <p class="text-xs text-slate-500">Printed {{ now()->format('j F Y') }}</p>
            </div>
        </header>

        <div class="flex items-start gap-5 py-5">
            @if ($student->photo_path)
                <img src="{{ Storage::disk('public')->url($student->photo_path) }}"
                     alt="{{ $student->full_name }}"
                     class="size-24 shrink-0 rounded-lg border border-slate-200 object-cover">
            @else
                <span class="grid size-24 shrink-0 place-items-center rounded-lg border border-slate-200 bg-slate-50 font-display text-2xl font-bold text-slate-400">
                    {{ Str::substr($student->first_name ?? '', 0, 1) }}{{ Str::substr($student->last_name ?? '', 0, 1) }}
                </span>
            @endif

            <dl class="grid flex-1 gap-x-6 gap-y-3 sm:grid-cols-3">
                @foreach ([
                    'Name' => $student->full_name,
                    'Student number' => $student->student_number,
                    'Status' => Str::headline($student->status),
                    'Gender' => $student->gender,
                    'Date of birth' => $student->date_of_birth?->format('j F Y'),
                    'Nationality' => $student->nationality,
                    'Class' => $current?->section?->full_name,
                    'Academic year' => $year?->name,
                    'On the roll since' => $student->created_at?->format('j F Y'),
                ] as $label => $value)
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <section class="border-t border-slate-200 py-4">
            <h2 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Parents and guardians</h2>

            @if ($student->guardians->isEmpty())
                <p class="mt-2 text-sm text-slate-500">None on file.</p>
            @else
                <table class="mt-2 w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-y border-slate-200 text-left">
                            <th scope="col" class="py-1.5 pr-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th scope="col" class="py-1.5 pr-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Relationship</th>
                            <th scope="col" class="py-1.5 pr-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                            <th scope="col" class="py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Primary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($student->guardians as $guardian)
                            <tr class="border-b border-slate-100">
                                <td class="py-1.5 pr-3 font-medium text-slate-900">{{ $guardian->full_name }}</td>
                                <td class="py-1.5 pr-3 text-slate-600">{{ $guardian->pivot->relationship }}</td>
                                <td class="py-1.5 pr-3 text-slate-600">{{ $guardian->phone ?: '—' }}</td>
                                <td class="py-1.5 text-slate-600">{{ $guardian->pivot->is_primary ? 'Yes' : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="border-t border-slate-200 py-4">
            <h2 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placement history</h2>

            @if ($student->enrollments->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Not yet placed in a class.</p>
            @else
                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                    @foreach ($student->enrollments->sortByDesc(fn ($e) => $e->academicYear?->starts_on) as $enrollment)
                        <li class="flex justify-between gap-4 border-b border-slate-100 py-1">
                            <span class="font-medium text-slate-900">{{ $enrollment->section?->full_name ?? '—' }}</span>
                            <span class="text-slate-500">{{ $enrollment->academicYear?->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="border-t border-slate-200 py-4">
            <h2 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Documents on file</h2>

            {{-- Titles and status only. The record sheet says what the school
                 holds; the files themselves stay behind their own
                 authorization, which is the whole point of section 42. --}}
            @if ($student->documents->isEmpty())
                <p class="mt-2 text-sm text-slate-500">No documents on file.</p>
            @else
                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                    @foreach ($student->documents as $document)
                        <li class="flex justify-between gap-4 border-b border-slate-100 py-1">
                            <span>
                                <span class="font-medium text-slate-900">{{ $document->title }}</span>
                                @if ($document->type)
                                    <span class="text-slate-500">· {{ $document->type->name }}</span>
                                @endif
                            </span>
                            <span class="text-slate-500">{{ Str::headline($document->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="mt-6 flex items-end justify-between gap-6 border-t border-slate-200 pt-5">
            <div>
                <div class="h-9 w-44 border-b border-slate-400"></div>
                <p class="mt-1 text-xs text-slate-500">Registrar</p>
            </div>

            @if (! empty($verifyCode))
                <div class="flex items-center gap-3">
                    <div class="shrink-0">{!! $verifyCode !!}</div>
                    <p class="max-w-56 text-xs leading-relaxed text-slate-500">
                        Scan to confirm this student number against {{ $school?->name }}'s records.
                        Only enrolment status is shown.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
