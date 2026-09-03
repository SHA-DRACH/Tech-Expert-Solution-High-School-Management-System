@php
    /*
     | Section 9 names eight things this page should cover. Each is a content
     | block the school writes in the website editor, matched by heading; where
     | nothing has been written the section is simply left out rather than
     | showing an empty heading.
     */
    $blocks = collect($page?->blocks() ?? []);

    $find = fn (string $needle) => $blocks->first(
        fn (array $b) => str_contains(Str::lower($b['heading'] ?? ''), Str::lower($needle))
    );

    $sections = collect([
        ['key' => 'history', 'label' => 'Our story', 'icon' => '◈', 'block' => $find('story') ?? $find('history')],
        ['key' => 'mission', 'label' => 'Our mission', 'icon' => '✦', 'block' => $find('mission')],
        ['key' => 'vision', 'label' => 'Our vision', 'icon' => '◉', 'block' => $find('vision')],
        ['key' => 'values', 'label' => 'Our values', 'icon' => '❋', 'block' => $find('values')],
        ['key' => 'principal', 'label' => "Principal's message", 'icon' => '✎', 'block' => $find('principal')],
        ['key' => 'facilities', 'label' => 'Facilities', 'icon' => '⌂', 'block' => $find('facilit')],
        ['key' => 'policies', 'label' => 'School policies', 'icon' => '☰', 'block' => $find('polic')],
    ])->filter(fn (array $s) => $s['block'] !== null)->values();

    // Anything the school wrote that did not match a known heading still shows.
    $matched = $sections->pluck('block.heading')->filter()->all();
    $extra = $blocks->reject(fn (array $b) => in_array($b['heading'] ?? '', $matched, true))->values();
@endphp

<x-layouts.public :school="$school" title="About us" :social-links="$socialLinks">
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
                    <li class="font-medium text-white/90" aria-current="page">About us</li>
                </ol>
            </nav>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl"
                data-aos="fade-up" data-aos-delay="80">
                {{ $page?->title ?? 'About us' }}
            </h1>

            <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="160">
                {{ $page?->summary ?? 'A supportive learning community dedicated to character, achievement, and service.' }}
            </p>
        </div>
    </section>

    @if ($statistics)
        <section class="border-b border-slate-200 bg-slate-50">
            <dl class="mx-auto grid max-w-7xl grid-cols-2 gap-y-6 px-4 py-10 sm:px-6 lg:grid-cols-4">
                @foreach ($statistics as $stat)
                    <div class="text-center" data-aos="fade-up" data-aos-delay="{{ $loop->index * 60 }}">
                        <dd class="font-display text-3xl font-bold text-slate-900">
                            {{-- The real figure, not a nought. The counter module blanks this to zero
                                     itself at the moment the animation starts; rendering 0 up front means a
                                     visitor whose animation never runs -- JavaScript off, an old browser --
                                     is told the school has no students. --}}
                                <span data-counter="{{ $stat['value'] }}" data-suffix="{{ $stat['suffix'] }}">{{ number_format($stat['value']) }}{{ $stat['suffix'] }}</span>
                        </dd>
                        <dt class="mt-1 text-xs uppercase tracking-widest text-slate-500">{{ $stat['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="section">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            @if ($sections->isEmpty() && $extra->isEmpty())
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-slate-600">
                        {{ $school->name }} has not published its About content yet. Please
                        <a href="{{ route('public.contact') }}" class="font-medium text-brand hover:underline">contact the office</a>
                        for information about the school.
                    </p>
                </div>
            @else
                <div class="grid gap-12 lg:grid-cols-[16rem_1fr]">
                    {{-- On-page contents: a long About page is easier to navigate with one. --}}
                    <nav class="hidden lg:block" aria-label="On this page">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">On this page</p>

                        <ul class="mt-4 space-y-1 border-l border-slate-200 text-sm">
                            @foreach ($sections as $section)
                                <li>
                                    <a href="#{{ $section['key'] }}"
                                       class="-ml-px block border-l-2 border-transparent py-1.5 pl-4 text-slate-600 transition-colors hover:border-brand hover:text-brand">
                                        {{ $section['block']['heading'] ?? $section['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>

                    <div class="min-w-0 space-y-14">
                        @foreach ($sections as $section)
                            <article id="{{ $section['key'] }}" class="scroll-mt-28" data-aos="fade-up">
                                <div class="flex items-center gap-3">
                                    <span class="icon-badge" aria-hidden="true">{{ $section['icon'] }}</span>
                                    <h2 class="font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                                        {{ $section['block']['heading'] ?? $section['label'] }}
                                    </h2>
                                </div>

                                <div class="mt-5 space-y-4 text-slate-600">
                                    @foreach (preg_split('/\n\s*\n/', trim($section['block']['body'] ?? '')) as $paragraph)
                                        <p>{{ $paragraph }}</p>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach

                        @foreach ($extra as $block)
                            <article class="scroll-mt-28" data-aos="fade-up">
                                @if (! empty($block['heading']))
                                    <h2 class="font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                                        {{ $block['heading'] }}
                                    </h2>
                                @endif

                                <div class="mt-5 space-y-4 text-slate-600">
                                    @foreach (preg_split('/\n\s*\n/', trim($block['body'] ?? '')) as $paragraph)
                                        <p>{{ $paragraph }}</p>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{--
        Teaching staff, not leadership.

        An earlier version headed this section "School leadership". Nothing on
        the teacher record identifies who leads the school, so that heading was
        simply the first six published teachers presented as something they had
        not been recorded as being — a false claim about named people on a page
        parents read. If leadership belongs here later it needs a real field
        behind it, not a LIMIT.
    --}}
    @if ($staff->isNotEmpty())
        <section class="section section--surface">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="max-w-2xl" data-aos="fade-up">
                    <p class="eyebrow">Our staff</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        The people who teach here
                    </h2>
                </div>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($staff as $person)
                        <article class="card-soft p-6" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 70 }}">
                            <div class="flex items-center gap-4">
                                @if ($person->photo_path)
                                    <img src="{{ Storage::disk('public')->url($person->photo_path) }}" alt=""
                                         class="size-16 rounded-full object-cover" loading="lazy">
                                @else
                                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-brand/10 font-display text-lg font-bold text-brand">
                                        {{ Str::substr($person->first_name, 0, 1) }}{{ Str::substr($person->last_name, 0, 1) }}
                                    </span>
                                @endif

                                <div class="min-w-0">
                                    <h3 class="truncate font-display text-base font-bold text-slate-900">{{ $person->full_name }}</h3>
                                    <p class="truncate text-sm text-slate-500">{{ $person->department?->name ?? 'Teaching staff' }}</p>
                                </div>
                            </div>

                            @if ($person->biography)
                                <p class="mt-4 line-clamp-3 text-sm text-slate-600">{{ $person->biography }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="mt-8" data-aos="fade-up">
                    <a href="{{ route('public.teachers') }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        Meet all our teachers
                        <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                    </a>
                </div>
            </div>
        </section>
    @endif

    <section class="section section--ink">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="font-display text-2xl font-bold text-white sm:text-3xl" data-aos="fade-up">
                Come and see the school
            </h2>
            <p class="mt-3 text-white/75" data-aos="fade-up" data-aos-delay="80">
                Applications are open. Start online, or contact the office to arrange a visit.
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
