<x-layouts.app title="My classes">
    <x-ui.page-header title="My classes" description="The sections and subjects you are assigned to." />

    @if ($sections->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="◉" title="No classes assigned" description="Your school administrator assigns classes and subjects to you." />
        </x-ui.card>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($sections as $section)
                <article class="card-soft p-6" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 6) * 60 }}">
                    <span class="icon-badge" aria-hidden="true">◉</span>

                    <h2 class="mt-4 font-display text-base font-bold text-slate-900">{{ $section->full_name }}</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $section->enrollments_count }} {{ Str::plural('student', $section->enrollments_count) }}
                        @if ($section->room) · {{ $section->room }} @endif
                    </p>

                    @if ($section->my_subjects->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($section->my_subjects as $subject)
                                <x-ui.badge tone="info">{{ $subject }}</x-ui.badge>
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ route('teaching.students', ['section' => $section->id]) }}"
                       class="group mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        View students
                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </a>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>
