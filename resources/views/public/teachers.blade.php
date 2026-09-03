@php
    // Grouped by department so the page reads as a staff list, not a wall.
    $byDepartment = $teachers->groupBy(fn ($teacher) => $teacher->department?->name ?? 'Teaching staff');
@endphp

<x-layouts.public :school="$school" title="Teachers" :social-links="$socialLinks">
    <section class="section--ink py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <nav aria-label="Breadcrumb" data-aos="fade-up">
                <ol class="flex items-center gap-2 text-xs text-white/60">
                    <li><a href="{{ route('home') }}" class="link-underline hover:text-white">Home</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="font-medium text-white/90" aria-current="page">Teachers</li>
                </ol>
            </nav>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl"
                data-aos="fade-up" data-aos-delay="80">
                Our teachers
            </h1>

            <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="160">
                The people in front of the class at {{ $school->short_name ?? $school->name }}, and what
                they bring to it.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            @if ($teachers->isEmpty())
                <div class="mx-auto max-w-xl py-10 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-slate-100 text-xl text-slate-400"
                          aria-hidden="true">❋</span>

                    <h2 class="mt-5 font-display text-lg font-bold text-slate-900">No profiles published yet</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $school->name }} has not published its staff profiles.
                        <a href="{{ route('public.contact') }}" class="font-medium text-brand hover:underline">Contact the office</a>
                        to ask about our teaching staff.
                    </p>
                </div>
            @else
                @foreach ($byDepartment as $department => $members)
                    <div class="{{ $loop->first ? '' : 'mt-14' }}">
                        <div class="flex items-end justify-between gap-4" data-aos="fade-up">
                            <div>
                                <p class="eyebrow">{{ $department }}</p>
                                <h2 class="mt-3 font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                                    {{ $members->count() }} {{ Str::plural('teacher', $members->count()) }}
                                </h2>
                            </div>
                        </div>

                        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($members as $teacher)
                                <article class="card-soft p-7" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 80 }}">
                                    <div class="flex items-center gap-4">
                                        @if ($teacher->photo_path)
                                            <img src="{{ Storage::disk('public')->url($teacher->photo_path) }}" alt=""
                                                 class="size-20 rounded-full object-cover" loading="lazy">
                                        @else
                                            <span class="grid size-20 shrink-0 place-items-center rounded-full bg-brand/10 font-display text-xl font-bold text-brand">
                                                {{ Str::substr($teacher->first_name, 0, 1) }}{{ Str::substr($teacher->last_name, 0, 1) }}
                                            </span>
                                        @endif

                                        <div class="min-w-0">
                                            <h3 class="font-display text-base font-bold text-slate-900">
                                                {{ $teacher->full_name }}
                                            </h3>
                                            <p class="mt-0.5 text-sm text-slate-500">
                                                {{ $teacher->department?->name ?? 'Teaching staff' }}
                                            </p>
                                            @if ($teacher->experience_years)
                                                <p class="mt-0.5 text-xs text-slate-400">
                                                    {{ $teacher->experience_years }} years of experience
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($teacher->biography)
                                        <p class="mt-5 text-sm text-slate-600">{{ $teacher->biography }}</p>
                                    @endif

                                    {{--
                                        Only qualifications the school has marked public. Nothing
                                        private about a member of staff appears here.
                                    --}}
                                    @if ($teacher->publicQualifications->isNotEmpty())
                                        <div class="mt-5 border-t border-slate-100 pt-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Qualifications
                                            </p>

                                            <ul class="mt-2 space-y-1.5">
                                                @foreach ($teacher->publicQualifications as $qualification)
                                                    <li class="flex gap-2 text-sm text-slate-600">
                                                        <span aria-hidden="true" class="text-brand">◈</span>
                                                        <span>
                                                            {{ $qualification->title }}
                                                            @if ($qualification->institution)
                                                                <span class="text-slate-400">· {{ $qualification->institution }}</span>
                                                            @endif
                                                            @if ($qualification->awarded_year)
                                                                <span class="text-slate-400">· {{ $qualification->awarded_year }}</span>
                                                            @endif
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    <section class="section section--ink">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="font-display text-2xl font-bold text-white sm:text-3xl" data-aos="fade-up">
                Join a school that knows your child
            </h2>
            <p class="mt-3 text-white/75" data-aos="fade-up" data-aos-delay="80">
                Small classes, teachers who notice, and reporting families can actually follow.
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3" data-aos="fade-up" data-aos-delay="160">
                <a href="{{ route('apply') }}"
                   class="press inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-white/90">
                    Apply for admission
                </a>
                <a href="{{ route('public.contact') }}"
                   class="press inline-flex items-center rounded-lg border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    Contact the office
                </a>
            </div>
        </div>
    </section>
</x-layouts.public>
