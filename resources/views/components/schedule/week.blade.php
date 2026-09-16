@props([
    'week',             // Collection keyed by ISO day, from ClassSchedule::week()
    'days',             // [isoDay => name], from ClassSchedule::days()
    'empty' => 'The class timetable will appear here once the school publishes it.',
])

@php
    use App\Services\ClassSchedule;

    $today = now()->dayOfWeekIso;
    $clock = now()->format('H:i:s');

    // Written out in full: Tailwind only builds classes it can see whole.
    $columns = match (min(count($days), 7)) {
        1 => 'lg:grid-cols-1',
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        4 => 'lg:grid-cols-4',
        5 => 'lg:grid-cols-5',
        6 => 'lg:grid-cols-6',
        default => 'lg:grid-cols-7',
    };
@endphp

@if ($week->isEmpty())
    <x-ui.empty-state icon="◷" title="No schedule yet" :description="$empty" />
@else
    {{--
        A column per day on a wide screen, stacked on a phone - most parents
        will read this on a phone. Today is marked, and so is the lesson
        running right now, because "where is my child at this minute?" is
        the question this answers most often.
    --}}
    <div {{ $attributes->merge(['class' => 'grid gap-3 sm:grid-cols-2 '.$columns]) }}>
        @foreach ($days as $day => $name)
            @php $lessons = $week->get($day) ?? collect(); @endphp

            <section @class([
                'min-w-0 rounded-lg border',
                'border-brand/40 bg-brand/5 ring-1 ring-brand/20' => $day === $today,
                'border-slate-200 bg-white' => $day !== $today,
            ])>
                <header class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $name }}</h3>
                    @if ($day === $today)
                        <span class="rounded-full bg-brand px-2 py-0.5 text-[10px] font-semibold uppercase text-white">Today</span>
                    @endif
                </header>

                @forelse ($lessons as $lesson)
                    @php $running = $day === $today && $lesson->starts_at <= $clock && $lesson->ends_at > $clock; @endphp

                    <div @class([
                        'border-b border-slate-100 px-3 py-2 last:border-0',
                        'bg-emerald-50' => $running,
                    ])>
                        <p class="font-mono text-[11px] text-slate-500">
                            {{ ClassSchedule::time($lesson->starts_at) }} – {{ ClassSchedule::time($lesson->ends_at) }}
                            @if ($running)
                                <span class="ml-1 font-sans font-semibold text-emerald-700">· Now</span>
                            @endif
                        </p>
                        <p class="truncate text-sm font-medium text-slate-900">{{ $lesson->subject?->name }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $lesson->teacher?->full_name ?? 'Teacher to be confirmed' }}{{ $lesson->room ? ' · '.$lesson->room : '' }}
                        </p>
                    </div>
                @empty
                    <p class="px-3 py-3 text-xs text-slate-400">No lessons</p>
                @endforelse
            </section>
        @endforeach
    </div>
@endif
