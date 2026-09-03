<x-layouts.app title="Attendance" heading="Attendance">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Attendance' => null]" />

    <x-ui.page-header title="Attendance" :description="'Marking for '.$date->format('l, j F Y')" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summary as $status => $count)
            <x-ui.stat :label="Str::headline($status)" :value="(string) $count" note="Marked today" />
        @endforeach
    </div>

    <x-ui.card class="mt-6" :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="w-52">
                <label for="section" class="sr-only">Class</label>
                <x-ui.select name="section" :selected="$section?->id"
                             :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
            </div>

            <div class="w-44">
                <label for="date" class="sr-only">Date</label>
                <x-ui.input name="date" type="date" :value="$date->toDateString()" />
            </div>

            <x-ui.button type="submit" variant="secondary">Show register</x-ui.button>
        </form>

        @if ($section === null)
            <x-ui.empty-state icon="◉" title="No classes yet" description="Create classes and sections before recording attendance." />
        @elseif ($students->isEmpty())
            <x-ui.empty-state icon="◉" title="No students in this class" description="Enroll students into this section first." />
        @else
            <form method="POST" action="{{ route('attendance.store') }}">
                @csrf
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <x-ui.table :headings="['Student', 'Number', 'Mark']">
                    @foreach ($students as $student)
                        @php $current = $existing[$student->id]->status ?? 'present'; @endphp

                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $student->full_name }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $student->student_number }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($statuses as $status)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status[{{ $student->id }}]" value="{{ $status }}"
                                                   class="peer sr-only" @checked($current === $status)>
                                            <span class="block rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 transition
                                                         hover:border-slate-300
                                                         peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white">
                                                {{ Str::headline($status) }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>

                @can('attendance.record')
                    <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                        <x-ui.button type="submit">Save register</x-ui.button>
                    </div>
                @endcan
            </form>
        @endif
    </x-ui.card>
</x-layouts.app>
