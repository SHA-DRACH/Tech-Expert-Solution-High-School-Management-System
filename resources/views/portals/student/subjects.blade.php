<x-layouts.app title="My subjects">
    <x-ui.page-header
        title="My subjects"
        :description="$section ? 'What you take in '.$section->full_name.'.' : 'You are not enrolled in a class yet.'"
    />

    @if ($subjects->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="◈"
                title="No subjects listed"
                description="Your class has no subjects attached to it yet. The school office sets these up."
            />
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($subjects as $subject)
                @php $takenBy = $teaching->get($subject->id, collect()); @endphp

                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-display text-base font-bold text-slate-900">{{ $subject->name }}</h2>
                            <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $subject->code }}</p>
                        </div>

                        @if ($subject->is_core)
                            <x-ui.badge tone="info">Core</x-ui.badge>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-slate-500">Department</dt>
                            <dd class="text-right text-slate-800">{{ $subject->department?->name ?? '—' }}</dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="shrink-0 text-slate-500">Teacher</dt>
                            <dd class="text-right text-slate-800">
                                {{ $takenBy->pluck('teacher')->filter()->map->full_name->unique()->join(', ') ?: '—' }}
                            </dd>
                        </div>
                    </dl>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
