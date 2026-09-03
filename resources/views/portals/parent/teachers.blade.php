@php
    use App\Support\Money;

@endphp
<x-layouts.app title="Teachers">
    <x-ui.page-header
        title="Teaching staff"
        description="Professional information the school has chosen to publish."
    />

    @if ($teachers->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="❋" title="No profiles published" description="The school has not published any staff profiles yet." />
        </x-ui.card>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teachers as $teacher)
                <article class="card-soft p-6" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 6) * 60 }}">
                    <div class="flex items-center gap-3">
                        @if ($teacher->photo_path)
                            <img src="{{ Storage::disk('public')->url($teacher->photo_path) }}" alt=""
                                 class="size-12 rounded-full object-cover">
                        @else
                            <span class="grid size-12 place-items-center rounded-full bg-brand/10 font-display text-sm font-bold text-brand">
                                {{ Str::substr($teacher->first_name, 0, 1) }}{{ Str::substr($teacher->last_name, 0, 1) }}
                            </span>
                        @endif

                        <div class="min-w-0">
                            <p class="truncate font-display text-sm font-bold text-slate-900">{{ $teacher->full_name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $teacher->department?->name ?? 'Teaching staff' }}</p>
                        </div>
                    </div>

                    @if ($teacher->experience_years)
                        <p class="mt-3 text-xs text-slate-500">{{ $teacher->experience_years }} years of experience</p>
                    @endif

                    @if ($teacher->biography)
                        <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $teacher->biography }}</p>
                    @endif

                    @if ($teacher->publicQualifications->isNotEmpty())
                        <ul class="mt-3 space-y-1">
                            @foreach ($teacher->publicQualifications as $qualification)
                                <li class="text-xs text-slate-500">
                                    {{ $qualification->title }}@if ($qualification->institution) · {{ $qualification->institution }}@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>
