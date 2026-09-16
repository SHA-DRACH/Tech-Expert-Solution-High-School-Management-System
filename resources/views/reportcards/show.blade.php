@php
    $isStaff = auth()->user()->hasPermission('reportcards.view');

    // What a card prints is the school's decision: some rank the class,
    // some deliberately do not, and some keep attendance off the card.
    $settings = app(\App\Services\SchoolSettings::class)->all();
@endphp

<x-layouts.app :title="'Report card · '.$card->student?->full_name" heading="Report card">
    @if ($isStaff)
        <x-ui.breadcrumbs :trail="[
            'Overview' => route('dashboard'),
            'Report cards' => route('reportcards.index'),
            $card->student?->full_name => null,
        ]" />
    @endif

    <div class="mb-4 flex flex-wrap justify-end gap-2 print:hidden">
        @if ($isStaff)
            <x-ui.button :href="route('reportcards.index')" variant="secondary">Back to report cards</x-ui.button>
        @endif
        {{-- A file the family keeps, which opens with no internet connection
             and prints to paper or PDF from any browser. --}}
        <x-ui.button :href="route('reportcards.download', $card)" variant="secondary">Download</x-ui.button>

        <x-ui.button onclick="window.print()">Print</x-ui.button>
    </div>

    @if ($card->status !== 'published')
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 print:hidden">
            <p class="text-sm font-medium text-amber-900">This is a draft</p>
            <p class="text-sm text-amber-800">It is not visible to the parent or student until it is published.</p>
        </div>
    @endif

    {{-- Deliberately plain so it prints cleanly on any printer. --}}
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
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Report card</p>
                <p class="text-sm font-medium text-slate-900">{{ $card->term?->name ?? 'Full year' }}</p>
                <p class="text-xs text-slate-500">{{ $card->academicYear?->name }}</p>
            </div>
        </header>

        {{-- Student identity, with the photograph the spec asks for. --}}
        <div class="flex items-start gap-5 py-5">
            @if ($card->student?->photo_path)
                <img src="{{ Storage::disk('public')->url($card->student->photo_path) }}"
                     alt="{{ $card->student->full_name }}"
                     class="size-24 shrink-0 rounded-lg border border-slate-200 object-cover">
            @else
                {{-- A named placeholder rather than an empty box, so a card
                     without a photograph still prints as a finished document. --}}
                <span class="grid size-24 shrink-0 place-items-center rounded-lg border border-slate-200 bg-slate-50 font-display text-2xl font-bold text-slate-400">
                    {{ Str::substr($card->student?->first_name ?? '', 0, 1) }}{{ Str::substr($card->student?->last_name ?? '', 0, 1) }}
                </span>
            @endif

            <dl class="grid flex-1 gap-x-6 gap-y-3 sm:grid-cols-3">
                @foreach ([
                    'Student' => $card->student?->full_name,
                    'Student ID' => $card->student?->student_number,
                    'Class' => $card->section?->full_name,
                    'Academic year' => $card->academicYear?->name,
                    'Period' => $card->term?->name ?? 'Full year',
                ] + ($settings['reportcard_show_position']
                    ? ['Position' => $card->position ? $card->position.' of '.$card->class_size : '—']
                    : []) as $label => $value)
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Subject results --}}
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-y border-slate-300 bg-slate-50 text-left">
                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Subject</th>
                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Score</th>
                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Grade</th>
                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Position</th>
                    <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">Remark</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($card->items as $item)
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 font-medium text-slate-900">{{ $item->subject?->name }}</td>
                        <td class="px-3 py-2 tabular-nums text-slate-700">{{ rtrim(rtrim((string) $item->score, '0'), '.') }}%</td>
                        <td class="px-3 py-2 font-semibold text-slate-900">{{ $item->grade ?? '—' }}</td>
                        <td class="px-3 py-2 tabular-nums text-slate-600">{{ $item->position ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-600">{{ $item->remark ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">
                            No approved subject results for this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            <tfoot>
                @php
                    /*
                     | Section 36 asks for a total as well as an average, and a
                     | Liberian report card is normally read that way round: the
                     | total is what a parent adds up against, the average is
                     | what the grade comes from. Both are shown rather than
                     | leaving one to be worked out by hand.
                     */
                    $marked = $card->items->filter(fn ($item) => $item->score !== null);
                    $total = $marked->sum(fn ($item) => (float) $item->score);
                @endphp

                @if ($marked->isNotEmpty())
                    <tr class="border-t border-slate-300">
                        <th scope="row" class="px-3 py-2 text-left text-sm font-medium text-slate-700">
                            Total ({{ $marked->count() }} {{ Str::plural('subject', $marked->count()) }})
                        </th>
                        <td class="px-3 py-2 text-sm font-semibold tabular-nums text-slate-900" colspan="4">
                            {{ rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') }}
                            <span class="font-normal text-slate-500">out of {{ $marked->count() * 100 }}</span>
                        </td>
                    </tr>
                @endif

                <tr class="border-t-2 border-slate-800">
                    <th scope="row" class="px-3 py-2.5 text-left text-sm font-semibold text-slate-900">Overall average</th>
                    <td class="px-3 py-2.5 font-display text-base font-bold tabular-nums text-slate-900" colspan="4">
                        {{ $card->average }}%
                    </td>
                </tr>
            </tfoot>
        </table>

        {{-- Attendance --}}
        @if ($settings['reportcard_show_attendance'])
        <div class="mt-5 grid gap-4 rounded-lg bg-slate-50 p-4 sm:grid-cols-3">
            @foreach ([
                'Days present' => $card->days_present ?? '—',
                'Days recorded' => $card->days_total ?? '—',
                'Attendance' => $card->attendancePercentage() === null ? '—' : $card->attendancePercentage().'%',
            ] as $label => $value)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-0.5 text-sm font-medium tabular-nums text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Comments --}}
        <div class="mt-5 space-y-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Class teacher's comment</p>
                <p class="mt-1 min-h-10 border-b border-dotted border-slate-300 pb-1 text-sm text-slate-700">
                    {{ $card->teacher_comment }}
                </p>
            </div>

            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Principal's comment</p>
                <p class="mt-1 min-h-10 border-b border-dotted border-slate-300 pb-1 text-sm text-slate-700">
                    {{ $card->principal_comment }}
                </p>
            </div>

            {{-- The school's standing note, beneath the comments written for this child. --}}
            @if (filled($settings['reportcard_remark']))
                <p class="whitespace-pre-line pt-1 text-xs text-slate-500">{{ $settings['reportcard_remark'] }}</p>
            @endif
        </div>

        {{-- Signatures --}}
        <div class="mt-8 flex items-end justify-between gap-6">
            <div>
                <div class="h-9 w-44 border-b border-slate-400"></div>
                <p class="mt-1 text-xs text-slate-500">
                    Class teacher{{ $card->section?->classTeacher ? ' — '.$card->section->classTeacher->full_name : '' }}
                </p>
            </div>

            <div>
                <div class="h-9 w-44 border-b border-slate-400"></div>
                <p class="mt-1 text-xs text-slate-500">Principal</p>
            </div>

            <div class="text-right">
                <p class="text-xs text-slate-500">Issued</p>
                <p class="text-sm font-medium text-slate-900">
                    {{ ($card->published_at ?? $card->updated_at)?->format('j F Y') }}
                </p>
            </div>
        </div>

        {{--
            The verification code. A grade sheet is the document most worth
            forging - it decides admission to the next school - so the point of
            printing it is that anyone holding the paper can check it against
            the school's own records without needing an account here. Drafts
            carry no code, because there is nothing yet to confirm.
        --}}
        @if (! empty($verifyCode))
            <div class="mt-6 flex items-center gap-3 border-t border-slate-200 pt-4">
                <div class="shrink-0">{!! $verifyCode !!}</div>
                <p class="text-xs leading-relaxed text-slate-500">
                    Scan to verify this report card against {{ $school?->name }}'s records.<br>
                    Marks are not shown by the check — only that this card was issued.
                </p>
            </div>
        @endif
    </div>

    {{-- Staff may add the written comments; parents and students only read them. --}}
    @can('reportcards.generate')
        <x-ui.card class="mx-auto mt-6 max-w-3xl print:hidden" title="Comments" description="These appear on the printed report card.">
            <form method="POST" action="{{ route('reportcards.update', $card) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-ui.field label="Class teacher's comment" name="teacher_comment">
                    <x-ui.textarea name="teacher_comment" rows="3" :value="$card->teacher_comment" />
                </x-ui.field>

                <x-ui.field label="Principal's comment" name="principal_comment">
                    <x-ui.textarea name="principal_comment" rows="3" :value="$card->principal_comment" />
                </x-ui.field>

                <div class="flex justify-end">
                    <x-ui.button type="submit">Save comments</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan
</x-layouts.app>
