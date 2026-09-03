<x-layouts.app title="My class">
    <x-ui.page-header
        title="My class"
        :description="$section ? $section->full_name : 'You are not enrolled in a class yet.'"
    />

    @if ($section === null)
        <x-ui.card>
            <x-ui.empty-state
                icon="⌘"
                title="Not enrolled yet"
                description="Once the school places you in a class it will appear here."
            />
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Class" :value="$section->full_name" note="Your placement" />
            <x-ui.stat label="Class teacher" :value="$classTeacher?->full_name ?? 'Not assigned'" note="Your first point of contact" />
            {{-- A count, not a roster. A student has no business reading their
                 classmates' records. --}}
            <x-ui.stat label="Class size" :value="(string) $classmates" note="Students in this class" />
        </div>

        <x-ui.card class="mt-6" title="Who teaches you" :padded="false">
            @forelse ($teachers->groupBy('teacher_id') as $rows)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                    <span class="font-medium text-slate-900">{{ $rows->first()->teacher?->full_name ?? 'Not assigned' }}</span>
                    <span class="text-sm text-slate-500">
                        {{ $rows->pluck('subject')->filter()->map->name->unique()->sort()->join(', ') }}
                    </span>
                </div>
            @empty
                <x-ui.empty-state title="No teachers assigned yet" />
            @endforelse
        </x-ui.card>

        @if ($section->room)
            <p class="mt-4 text-sm text-slate-500">Room {{ $section->room }}</p>
        @endif
    @endif
</x-layouts.app>
