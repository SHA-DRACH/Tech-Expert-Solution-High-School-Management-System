@php $t = App\Support\SiteContent::for($page ?? null, 'contact', $school); @endphp

<x-layouts.public :school="$school" :title="$title">
    {{-- Page banner in the brand gradient, matching the homepage hero. --}}
    <section class="section--ink relative overflow-hidden py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <nav aria-label="Breadcrumb" data-aos="fade-up">
                <ol class="flex items-center gap-2 text-xs text-white/60">
                    <li><a href="{{ route('home') }}" class="link-underline hover:text-white">Home</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="font-medium text-white/90" aria-current="page">{{ $title }}</li>
                </ol>
            </nav>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl"
                data-aos="fade-up" data-aos-delay="80">{{ $title }}</h1>

            <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="160">{{ $description }}</p>
        </div>
    </section>

    <section class="section">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-3">
            <div class="lg:col-span-2" data-aos="fade-right">
                @php $blocks = $page?->blocks() ?? []; @endphp

                @if ($blocks)
                    {{-- Written by the school in the website editor. --}}
                    @foreach ($blocks as $block)
                        <div class="{{ $loop->first ? '' : 'mt-10' }}">
                            @if (! empty($block['heading']))
                                @if ($loop->first)
                                    <p class="eyebrow">{{ $t('overview_eyebrow') }}</p>
                                @endif

                                <h2 class="mt-3 font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                                    {{ $block['heading'] }}
                                </h2>
                            @endif

                            @if (! empty($block['body']))
                                <div class="mt-5 space-y-4 text-slate-600">
                                    @foreach (preg_split('/\n\s*\n/', trim($block['body'])) as $paragraph)
                                        <p>{{ $paragraph }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    {{-- Nothing written yet: say so plainly rather than showing an empty page. --}}
                    <p class="eyebrow">{{ $t('overview_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                        {{ $school->name }}
                    </h2>

                    <div class="mt-5 space-y-4 text-slate-600">
                        <p>{{ $description }}</p>
                        <p>
                            {{ $t('fallback_body') }}
                        </p>
                    </div>
                @endif

                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button :href="route('apply')">Apply for admission</x-ui.button>
                    <x-ui.button :href="route('public.contact')" variant="secondary">Contact the office</x-ui.button>
                </div>
            </div>

            <aside class="card-soft h-fit p-7" data-aos="fade-left" data-aos-delay="80">
                <span class="icon-badge icon-badge--accent" aria-hidden="true">✆</span>

                <h2 class="mt-5 font-display text-lg font-bold text-slate-900">{{ $t('details_heading') }}</h2>

                <address class="mt-4 space-y-3 text-sm not-italic text-slate-600">
                    @if ($school->address)
                        <p class="flex gap-2.5"><span aria-hidden="true" class="text-brand">◎</span>{{ $school->address }}</p>
                    @endif
                    @if ($school->phone)
                        <p class="flex gap-2.5"><span aria-hidden="true" class="text-brand">✆</span>
                            <a href="tel:{{ $school->phone }}" class="link-underline hover:text-brand">{{ $school->phone }}</a></p>
                    @endif
                    @if ($school->email)
                        <p class="flex gap-2.5"><span aria-hidden="true" class="text-brand">✉</span>
                            <a href="mailto:{{ $school->email }}" class="link-underline hover:text-brand">{{ $school->email }}</a></p>
                    @endif
                </address>
            </aside>
        </div>
    </section>

    <section class="section section--surface">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="max-w-2xl" data-aos="fade-up">
                <p class="eyebrow">{{ $t('next_eyebrow') }}</p>
                <h2 class="mt-3 font-display text-2xl font-bold text-slate-900 sm:text-3xl">{{ $t('next_heading') }}</h2>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['✦', $t('next_1_title'), $t('next_1_body'), route('apply')],
                    ['◉', $t('next_2_title'), $t('next_2_body'), route('public.academics')],
                    ['❋', $t('next_3_title'), $t('next_3_body'), route('public.contact')],
                ] as $index => [$icon, $heading, $body, $url])
                    <a href="{{ $url }}" class="card-soft group block p-7"
                       data-aos="fade-up" data-aos-delay="{{ $index * 80 }}">
                        <span class="icon-badge" aria-hidden="true">{{ $icon }}</span>
                        <h3 class="mt-5 font-display text-base font-bold text-slate-900">{{ $heading }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $body }}</p>
                        <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                            {{ $t('next_link') }}
                            <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">→</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.public>
