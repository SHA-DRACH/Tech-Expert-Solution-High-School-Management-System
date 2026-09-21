@php
    // Blocks the school has written in the website editor, addressed by heading
    // so each homepage section can pick up its own copy.
    $blocks = collect($page?->blocks() ?? []);

    $block = fn (string $needle) => $blocks->first(
        fn (array $b) => str_contains(Str::lower($b['heading'] ?? ''), Str::lower($needle))
    );

    $welcome = $block('welcome') ?? $blocks->first();
    $mission = $block('mission');
    $vision = $block('vision');
    $values = $block('values');

    // Every heading and label below is the school's to change (Website › Home).
    $t = App\Support\SiteContent::for($page, 'home', $school);
@endphp

<x-layouts.public :school="$school" :social-links="$socialLinks">

    {{-- ---------------------------------------------------------------
         Hero: logo, name, motto, introduction, and the two calls to
         action the spec names. Text rises in on a stagger.
    ---------------------------------------------------------------- --}}
    <section class="hero relative overflow-hidden" aria-label="Welcome">
        @if ($page?->hero_image_path)
            <img src="{{ Storage::disk('public')->url($page->hero_image_path) }}" alt=""
                 class="absolute inset-0 size-full object-cover">
        @endif

        <div class="absolute inset-0"
             style="background-image: linear-gradient(100deg,
                    color-mix(in srgb, {{ $school->primary_color }} 94%, #000) 8%,
                    color-mix(in srgb, {{ $school->primary_color }} 82%, transparent) 52%,
                    color-mix(in srgb, {{ $school->secondary_color }} 40%, transparent) 100%)"></div>

        <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:py-28">
            <div class="hero-content max-w-2xl text-white">
                @if ($school->logo_path)
                    <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt=""
                         class="mb-7 size-20 rounded-2xl bg-white/10 object-cover p-1.5 backdrop-blur">
                @endif

                <p class="hero-eyebrow">{{ $t('hero_eyebrow') }}</p>

                <h1 class="font-display text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                    {{ $school->name }}
                </h1>

                @if ($school->motto)
                    <p class="hero-slogan mt-3 text-lg">{{ $school->motto }}</p>
                @endif

                @php
                    /*
                     | The motto is already shown above, so a summary that just
                     | repeats it would print the same line twice. Fall back to
                     | the standing introduction in that case.
                     */
                    $introduction = filled($page?->summary) && $page->summary !== $school->motto
                        ? $page->summary
                        : $t->summary();
                @endphp

                <p class="hero-lead mt-4 max-w-xl">{{ $introduction }}</p>

                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ route('apply') }}"
                       class="press inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-slate-900 shadow-lg transition hover:bg-white/90">
                        {{ $t('hero_primary') }}
                    </a>

                    <a href="{{ route('login') }}"
                       class="press inline-flex items-center rounded-lg border border-white/40 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/10">
                        {{ $t('hero_secondary') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- School statistics: figures count up as they scroll into view. --}}
    @if ($statistics)
        <section class="stat-strip" aria-label="School at a glance">
            <dl class="mx-auto grid max-w-7xl grid-cols-2 gap-y-4 px-4 py-10 sm:px-6 lg:grid-cols-4">
                @foreach ($statistics as $stat)
                    <div class="stat" data-aos="fade-up" data-aos-delay="{{ $loop->index * 70 }}">
                        <dd class="stat-value">
                            {{-- The real figure, not a nought. The counter module blanks this to zero
                                     itself at the moment the animation starts; rendering 0 up front means a
                                     visitor whose animation never runs -- JavaScript off, an old browser --
                                     is told the school has no students. --}}
                                <span data-counter="{{ $stat['value'] }}" data-suffix="{{ $stat['suffix'] }}">{{ number_format($stat['value']) }}{{ $stat['suffix'] }}</span>
                        </dd>
                        <dt class="stat-label">{{ $stat['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- Welcome message and about the school --}}
    <section class="section">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
            <div data-aos="fade-right">
                <p class="eyebrow">{{ $t('about_eyebrow') }}</p>

                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $t->has('about_heading') ? $t('about_heading') : ($welcome['heading'] ?? $t('about_heading')) }}
                </h2>

                <div class="mt-5 space-y-4 text-slate-600">
                    @foreach (! $t->has('about_body') && ! empty($welcome['body']) ? preg_split('/\n\s*\n/', trim($welcome['body'])) : $t->paragraphs('about_body') as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>

                <div class="mt-8">
                    <a href="{{ route('public.about') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        {{ $t('about_link') }}
                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </a>
                </div>
            </div>

            {{-- Mission, vision and core values --}}
            <div class="grid gap-4 sm:grid-cols-2" data-aos="fade-left" data-aos-delay="80">
                @foreach ([
                    ['✦', $t('card_1_title'), $t->has('card_1_body') ? $t('card_1_body') : ($mission['body'] ?? $t('card_1_body'))],
                    ['◈', $t('card_2_title'), $t->has('card_2_body') ? $t('card_2_body') : ($vision['body'] ?? $t('card_2_body'))],
                    ['❋', $t('card_3_title'), $t->has('card_3_body') ? $t('card_3_body') : ($values['body'] ?? $t('card_3_body'))],
                    ['◉', $t('card_4_title'), $t('card_4_body')],
                ] as $index => [$icon, $heading, $body])
                    <article class="card-soft p-6" data-aos="fade-up" data-aos-delay="{{ $index * 70 }}">
                        <span class="icon-badge" aria-hidden="true">{{ $icon }}</span>
                        <h3 class="mt-4 font-display text-base font-bold text-slate-900">{{ $heading }}</h3>
                        <p class="mt-2 line-clamp-4 text-sm text-slate-600">{{ Str::limit(strip_tags($body), 160) }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Academic programmes --}}
    <section class="section section--surface">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="max-w-2xl" data-aos="fade-up">
                <p class="eyebrow">{{ $t('programmes_eyebrow') }}</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $t('programmes_heading') }}
                </h2>
                <p class="mt-3 text-slate-600">
                    {{ $t('programmes_intro') }}
                </p>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ([
                    [$t('programme_1_title'), $t('programme_1_meta'), $t('programme_1_body')],
                    [$t('programme_2_title'), $t('programme_2_meta'), $t('programme_2_body')],
                    [$t('programme_3_title'), $t('programme_3_meta'), $t('programme_3_body')],
                ] as $index => [$title, $meta, $body])
                    <article class="card-soft p-7" data-aos="fade-up" data-aos-delay="{{ $index * 80 }}">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ $meta }}</p>
                        <h3 class="mt-2 font-display text-lg font-bold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $body }}</p>
                    </article>
                @endforeach
            </div>

            <div class="mt-8" data-aos="fade-up">
                <a href="{{ route('public.academics') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                    {{ $t('programmes_link') }}
                    <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                </a>
            </div>
        </div>
    </section>

    {{-- Why choose us --}}
    <section class="section">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="max-w-2xl" data-aos="fade-up">
                <p class="eyebrow">{{ $t('why_eyebrow') }}</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $t('why_heading') }}
                </h2>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['◉', $t('why_1_title'), $t('why_1_body')],
                    ['◈', $t('why_2_title'), $t('why_2_body')],
                    ['✦', $t('why_3_title'), $t('why_3_body')],
                    ['❋', $t('why_4_title'), $t('why_4_body')],
                ] as $index => [$icon, $title, $body])
                    <article class="card-soft p-6" data-aos="fade-up" data-aos-delay="{{ $index * 70 }}">
                        <span class="icon-badge icon-badge--accent" aria-hidden="true">{{ $icon }}</span>
                        <h3 class="mt-4 font-display text-base font-bold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $body }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured teachers --}}
    @if ($teachers->isNotEmpty())
        <section class="section section--surface">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-wrap items-end justify-between gap-4" data-aos="fade-up">
                    <div class="max-w-2xl">
                        <p class="eyebrow">{{ $t('teachers_eyebrow') }}</p>
                        <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ $t('teachers_heading') }}
                        </h2>
                    </div>

                    <a href="{{ route('public.teachers') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        {{ $t('teachers_link') }}
                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </a>
                </div>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($teachers as $teacher)
                        <article class="card-soft overflow-hidden text-center" data-aos="fade-up" data-aos-delay="{{ $loop->index * 70 }}">
                            <div class="p-6">
                                @if ($teacher->photo_path)
                                    <img src="{{ Storage::disk('public')->url($teacher->photo_path) }}" alt=""
                                         class="mx-auto size-24 rounded-full object-cover" loading="lazy">
                                @else
                                    <span class="mx-auto grid size-24 place-items-center rounded-full bg-brand/10 font-display text-2xl font-bold text-brand">
                                        {{ Str::substr($teacher->first_name, 0, 1) }}{{ Str::substr($teacher->last_name, 0, 1) }}
                                    </span>
                                @endif

                                <h3 class="mt-4 font-display text-base font-bold text-slate-900">{{ $teacher->full_name }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $teacher->department?->name ?? 'Teaching staff' }}</p>

                                @if ($teacher->experience_years)
                                    <p class="mt-1 text-xs text-slate-400">{{ $teacher->experience_years }} years of experience</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Announcements, news and events --}}
    @if ($announcements->isNotEmpty() || $news->isNotEmpty() || $events->isNotEmpty())
        <section class="section">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid gap-10 lg:grid-cols-3">

                    {{-- Latest news --}}
                    @if ($news->isNotEmpty())
                        <div class="lg:col-span-2">
                            <div class="flex flex-wrap items-end justify-between gap-3" data-aos="fade-up">
                                <div>
                                    <p class="eyebrow">{{ $t('news_eyebrow') }}</p>
                                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900">
                                        {{ $t('news_heading') }}
                                    </h2>
                                </div>

                                <a href="{{ route('public.news') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                                    {{ $t('news_link') }}
                                    <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                                </a>
                            </div>

                            <div class="mt-7 grid gap-6 sm:grid-cols-2">
                                @foreach ($news as $post)
                                    <article class="card-soft overflow-hidden" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                                        @if ($post->image_path)
                                            <div class="zoom-frame aspect-[16/10]">
                                                <img src="{{ Storage::disk('public')->url($post->image_path) }}" alt="" loading="lazy">
                                            </div>
                                        @endif

                                        <div class="p-5">
                                            <time datetime="{{ $post->published_at?->toDateString() }}"
                                                  class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                                {{ $post->published_at?->format('j F Y') }}
                                            </time>

                                            <h3 class="mt-2 font-display text-base font-bold text-slate-900">
                                                <a href="{{ route('public.news.show', $post->slug) }}" class="link-underline">
                                                    {{ $post->title }}
                                                </a>
                                            </h3>

                                            <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $post->excerpt }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="space-y-8">
                        {{-- Announcements --}}
                        @if ($announcements->isNotEmpty())
                            <div data-aos="fade-left">
                                <p class="eyebrow">{{ $t('announcements_eyebrow') }}</p>

                                <div class="mt-5 space-y-3">
                                    @foreach ($announcements as $announcement)
                                        <article class="card-soft p-5">
                                            <div class="flex items-start justify-between gap-2">
                                                <h3 class="font-display text-sm font-bold text-slate-900">
                                                    {{ $announcement->title }}
                                                </h3>
                                                @if ($announcement->is_emergency)
                                                    <span class="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                                        Urgent
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-1.5 line-clamp-3 text-sm text-slate-600">{{ $announcement->body }}</p>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Upcoming events --}}
                        @if ($events->isNotEmpty())
                            <div data-aos="fade-left" data-aos-delay="80">
                                <div class="flex items-end justify-between gap-3">
                                    <p class="eyebrow">{{ $t('events_eyebrow') }}</p>
                                    <a href="{{ route('public.events') }}" class="text-sm font-semibold text-brand hover:underline">
                                        {{ $t('events_link') }}
                                    </a>
                                </div>

                                <div class="mt-5 space-y-3">
                                    @foreach ($events as $event)
                                        <article class="card-soft flex items-start gap-4 p-4">
                                            <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-brand/8 text-center">
                                                <span class="block font-display text-lg font-bold leading-none text-brand">
                                                    {{ $event->starts_at->format('j') }}
                                                </span>
                                                <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-brand/70">
                                                    {{ $event->starts_at->format('M') }}
                                                </span>
                                            </div>

                                            <div class="min-w-0">
                                                <h3 class="font-display text-sm font-bold text-slate-900">{{ $event->title }}</h3>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    {{ $event->starts_at->format('H:i') }}{{ $event->location ? ' · '.$event->location : '' }}
                                                </p>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Gallery --}}
    @if ($gallery->isNotEmpty())
        <section class="section section--surface">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="flex flex-wrap items-end justify-between gap-4" data-aos="fade-up">
                    <div class="max-w-2xl">
                        <p class="eyebrow">{{ $t('gallery_eyebrow') }}</p>
                        <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ $t('gallery_heading') }}
                        </h2>
                    </div>

                    <a href="{{ route('public.gallery') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        {{ $t('gallery_link') }}
                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </a>
                </div>

                <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($gallery as $item)
                        <figure class="zoom-frame overflow-hidden rounded-xl border border-slate-200"
                                data-aos="fade-up" data-aos-delay="{{ ($loop->index % 4) * 60 }}">
                            <img src="{{ Storage::disk('public')->url($item->image_path) }}"
                                 alt="{{ $item->caption ?? 'School photograph' }}"
                                 loading="lazy" class="aspect-square w-full object-cover">
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Admission call to action --}}
    <section class="section section--ink">
        <div class="mx-auto max-w-7xl px-4 text-center sm:px-6">
            <p class="eyebrow eyebrow--onDark eyebrow--center justify-center" data-aos="fade-up">
                Admissions {{ now()->year }} / {{ now()->year + 1 }}
            </p>

            <h2 class="mx-auto mt-4 max-w-2xl font-display text-2xl font-bold tracking-tight text-white sm:text-4xl"
                data-aos="fade-up" data-aos-delay="80">
                {{ $t('cta_heading') }}
            </h2>

            <p class="mx-auto mt-4 max-w-xl text-white/75" data-aos="fade-up" data-aos-delay="160">
                {{ $t('cta_body') }}
            </p>

            <div class="mt-9 flex flex-wrap justify-center gap-3" data-aos="fade-up" data-aos-delay="220">
                <a href="{{ route('apply') }}"
                   class="press inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-white/90">
                    {{ $t('cta_primary') }}
                </a>

                <a href="{{ route('public.admissions') }}"
                   class="press inline-flex items-center rounded-lg border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    {{ $t('cta_secondary') }}
                </a>
            </div>
        </div>
    </section>

    {{-- Contact --}}
    <section class="section">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:items-center">
            <div data-aos="fade-right">
                <p class="eyebrow">{{ $t('contact_eyebrow') }}</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $t('contact_heading') }}
                </h2>
                <p class="mt-3 text-slate-600">
                    {{ $t('contact_body') }}
                </p>

                <div class="mt-7">
                    <a href="{{ route('public.contact') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        {{ $t('contact_link') }}
                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </a>
                </div>
            </div>

            <div class="card-soft p-7" data-aos="fade-left" data-aos-delay="80">
                <address class="space-y-4 text-sm not-italic text-slate-600">
                    @if ($school->address)
                        <p class="flex gap-3">
                            <span aria-hidden="true" class="text-brand">◎</span>
                            <span>{{ $school->address }}</span>
                        </p>
                    @endif

                    @if ($school->phone)
                        <p class="flex gap-3">
                            <span aria-hidden="true" class="text-brand">✆</span>
                            <a href="tel:{{ $school->phone }}" class="link-underline hover:text-brand">{{ $school->phone }}</a>
                        </p>
                    @endif

                    @if ($school->email)
                        <p class="flex gap-3">
                            <span aria-hidden="true" class="text-brand">✉</span>
                            <a href="mailto:{{ $school->email }}" class="link-underline hover:text-brand">{{ $school->email }}</a>
                        </p>
                    @endif

                    @if ($school->website)
                        <p class="flex gap-3">
                            <span aria-hidden="true" class="text-brand">⌘</span>
                            <a href="{{ $school->website }}" class="link-underline hover:text-brand"
                               target="_blank" rel="noopener">{{ $school->website }}</a>
                        </p>
                    @endif
                </address>
            </div>
        </div>
    </section>
</x-layouts.public>
