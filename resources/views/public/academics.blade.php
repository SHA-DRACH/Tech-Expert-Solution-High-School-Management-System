@php $t = App\Support\SiteContent::for($page, 'academics', $school); @endphp

<x-layouts.public :school="$school" :title="$t->title()" :social-links="$socialLinks">
    <section class="section--ink relative overflow-hidden py-16 sm:py-20">
        @if ($page?->hero_image_path)
            <img src="{{ Storage::disk('public')->url($page->hero_image_path) }}" alt=""
                 class="absolute inset-0 size-full object-cover opacity-25">
        @endif

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
            <nav aria-label="Breadcrumb" data-aos="fade-up">
                <ol class="flex items-center gap-2 text-xs text-white/60">
                    <li><a href="{{ route('home') }}" class="link-underline hover:text-white">Home</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="font-medium text-white/90" aria-current="page">{{ $t->title() }}</li>
                </ol>
            </nav>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl"
                data-aos="fade-up" data-aos-delay="80">
                {{ $t->title() }}
            </h1>

            <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="160">
                {{ $t->summary() }}
            </p>
        </div>
    </section>

    {{-- Whatever the school has written about its academic programme. --}}
    @if ($page && $page->blocks())
        <section class="section">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($page->blocks() as $block)
                        <article class="card-soft p-7" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 80 }}">
                            @if (! empty($block['heading']))
                                <h2 class="font-display text-lg font-bold text-slate-900">{{ $block['heading'] }}</h2>
                            @endif

                            <div class="mt-3 space-y-3 text-sm text-slate-600">
                                @foreach (preg_split('/\n\s*\n/', trim($block['body'] ?? '')) as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- The classes actually on the books, grouped by stage. --}}
    @if (! empty($stages))
        <section class="section section--surface">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="max-w-2xl" data-aos="fade-up">
                    <p class="eyebrow">{{ $t('classes_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ $t('classes_heading') }}
                    </h2>
                </div>

                <div class="mt-10 grid gap-8 lg:grid-cols-2">
                    @foreach ($stages as $stage => $classes)
                        <div class="card-soft p-7" data-aos="fade-up" data-aos-delay="{{ $loop->index * 90 }}">
                            <div class="flex items-center gap-3">
                                <span class="icon-badge" aria-hidden="true">◉</span>
                                <div>
                                    <h3 class="font-display text-lg font-bold text-slate-900">{{ $stage }}</h3>
                                    <p class="text-xs text-slate-500">
                                        {{ $classes->count() }} {{ Str::plural('class', $classes->count()) }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-2">
                                @foreach ($classes as $class)
                                    <span class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700">
                                        {{ $class->name }}
                                    </span>
                                @endforeach
                            </div>

                            @php $stageSubjects = $classes->flatMap->subjects->unique('id')->sortBy('name'); @endphp

                            @if ($stageSubjects->isNotEmpty() && $visibility->shows('subjects'))
                                <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $t('subjects_covered') }}
                                </p>
                                <p class="mt-2 text-sm text-slate-600">
                                    {{ $stageSubjects->pluck('name')->join(', ') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Departments and subjects --}}
    @if ($departments->isNotEmpty() || $subjects->isNotEmpty())
        <section class="section">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid gap-12 lg:grid-cols-2">
                    @if ($departments->isNotEmpty())
                        <div data-aos="fade-right">
                            <p class="eyebrow">{{ $t('departments_eyebrow') }}</p>
                            <h2 class="mt-3 font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                                {{ $t('departments_heading') }}
                            </h2>

                            <div class="mt-6 space-y-3">
                                @foreach ($departments as $department)
                                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-3.5">
                                        <div class="min-w-0">
                                            <p class="font-medium text-slate-900">{{ $department->name }}</p>
                                            @if ($department->description)
                                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $department->description }}</p>
                                            @endif
                                        </div>

                                        <span class="shrink-0 text-xs text-slate-500">
                                            {{ $department->subjects_count }} {{ Str::plural('subject', $department->subjects_count) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($subjects->isNotEmpty())
                        <div data-aos="fade-left" data-aos-delay="80">
                            <p class="eyebrow">{{ $t('subjects_eyebrow') }}</p>
                            <h2 class="mt-3 font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                                {{ $t('subjects_heading') }}
                            </h2>

                            <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <div class="divide-y divide-slate-100">
                                    @foreach ($subjects as $subject)
                                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-slate-900">{{ $subject->name }}</p>
                                                <p class="text-xs text-slate-500">{{ $subject->department?->name ?? 'General' }}</p>
                                            </div>

                                            @if ($subject->is_core)
                                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700">
                                                    Core
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Academic calendar --}}
    @if ($terms->isNotEmpty())
        <section class="section section--surface">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="max-w-2xl" data-aos="fade-up">
                    <p class="eyebrow">{{ $t('calendar_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ $t('calendar_heading') }}
                    </h2>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    @foreach ($terms as $term)
                        <article @class([
                            'card-soft p-6',
                            'ring-2 ring-brand/30' => $term->is_current,
                        ]) data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900">{{ $term->name }}</h3>
                                    <p class="text-xs text-slate-500">{{ $term->academicYear?->name }}</p>
                                </div>

                                @if ($term->is_current)
                                    <span class="shrink-0 rounded-full bg-brand/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand">
                                        Current
                                    </span>
                                @endif
                            </div>

                            <p class="mt-4 text-sm text-slate-600">
                                {{ $term->starts_on?->format('j F Y') }} &ndash; {{ $term->ends_on?->format('j F Y') }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section section--ink">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="font-display text-2xl font-bold text-white sm:text-3xl" data-aos="fade-up">
                {{ $t('cta_heading') }}
            </h2>
            <p class="mt-3 text-white/75" data-aos="fade-up" data-aos-delay="80">
                {{ $t('cta_body') }}
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3" data-aos="fade-up" data-aos-delay="160">
                <a href="{{ route('public.admissions') }}"
                   class="press inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-white/90">
                    {{ $t('cta_primary') }}
                </a>
                <a href="{{ route('apply') }}"
                   class="press inline-flex items-center rounded-lg border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    {{ $t('cta_secondary') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts.public>
