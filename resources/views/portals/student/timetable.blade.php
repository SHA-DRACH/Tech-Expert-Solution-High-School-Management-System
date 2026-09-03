<x-layouts.app title="My timetable">
    <x-ui.page-header title="My timetable" description="Your weekly lesson schedule." />

    @if ($entriesByDay->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="◷" title="No timetable yet" description="Your class timetable will appear here once it is published." />
        </x-ui.card>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($days as $number => $dayName)
                @continue (! $entriesByDay->has($number))

                <x-ui.card :title="$dayName" :padded="false" data-aos="fade-up" data-aos-delay="{{ ($number - 1) * 60 }}">
                    @foreach ($entriesByDay[$number] as $entry)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                            <span class="shrink-0 font-mono text-xs text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($entry->starts_at)->format('H:i') }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $entry->subject?->name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $entry->teacher?->full_name }}{{ $entry->room ? ' · '.$entry->room : '' }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
