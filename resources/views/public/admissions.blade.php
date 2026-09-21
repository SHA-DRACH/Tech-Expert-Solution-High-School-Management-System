@php
    use App\Support\Money;

    $blocks = collect($page?->blocks() ?? []);

    $find = fn (string $needle) => $blocks->first(
        fn (array $b) => str_contains(Str::lower($b['heading'] ?? ''), Str::lower($needle))
    );

    $requirements = $find('requirement') ?? $find('ask');
    $process = $find('how to apply') ?? $find('process');
    $next = $find('what happens next') ?? $find('next');

    $t = App\Support\SiteContent::for($page, 'admissions', $school);
@endphp

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

            <p class="eyebrow eyebrow--onDark mt-6" data-aos="fade-up" data-aos-delay="60">
                Admissions {{ now()->year }} / {{ now()->year + 1 }}
            </p>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl lg:text-5xl"
                data-aos="fade-up" data-aos-delay="120">
                {{ $t->title() }}
            </h1>

            <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="180">
                {{ $t->summary() }}
            </p>

            <div class="mt-8" data-aos="fade-up" data-aos-delay="240">
                <a href="{{ route('apply') }}"
                   class="press inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-slate-900 shadow-lg transition hover:bg-white/90">
                    {{ $t('hero_button') }}
                </a>
            </div>
        </div>
    </section>

    {{-- The admission process, as numbered steps. --}}
    <section class="section">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="max-w-2xl" data-aos="fade-up">
                <p class="eyebrow">{{ $t('process_eyebrow') }}</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $t('process_heading') }}
                </h2>

                @if (! empty($process['body']))
                    <div class="mt-4 space-y-3 text-slate-600">
                        @foreach (preg_split('/\n\s*\n/', trim($process['body'])) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            <ol class="mt-10 grid gap-6 md:grid-cols-4">
                @foreach ([
                    [$t('step_1_title'), $t('step_1_body')],
                    [$t('step_2_title'), $t('step_2_body')],
                    [$t('step_3_title'), $t('step_3_body')],
                    [$t('step_4_title'), $t('step_4_body')],
                ] as $index => [$title, $body])
                    <li class="relative" data-aos="fade-up" data-aos-delay="{{ $index * 90 }}">
                        {{-- Connector between steps on wide screens. --}}
                        @unless ($loop->last)
                            <span aria-hidden="true"
                                  class="absolute left-12 right-0 top-5 hidden h-px bg-slate-200 md:block"></span>
                        @endunless

                        <div class="relative">
                            <span class="grid size-10 place-items-center rounded-full bg-brand font-display text-sm font-bold text-white">
                                {{ $index + 1 }}
                            </span>

                            <h3 class="mt-4 font-display text-base font-bold text-slate-900">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-slate-600">{{ $body }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section section--surface">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="grid gap-10 lg:grid-cols-2">

                {{-- Required documents --}}
                <div data-aos="fade-right">
                    <p class="eyebrow">{{ $t('documents_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        {{ $t('documents_heading') }}
                    </h2>

                    @if (! empty($requirements['body']))
                        <div class="mt-4 space-y-3 text-slate-600">
                            @foreach (preg_split('/\n\s*\n/', trim($requirements['body'])) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    @endif

                    <ul class="mt-6 space-y-2.5">
                        @foreach ($documentTypes as $document)
                            <li class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-5 py-3.5"
                                data-aos="fade-up" data-aos-delay="{{ ($loop->index % 6) * 50 }}">
                                <span aria-hidden="true" class="mt-0.5 text-brand">✓</span>
                                <span class="text-sm text-slate-700">{{ $document }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 text-xs text-slate-500">
                        {{ $t('documents_note') }}
                    </p>
                </div>

                {{-- Available classes --}}
                <div data-aos="fade-left" data-aos-delay="80">
                    <p class="eyebrow">{{ $t('classes_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        {{ $t('classes_heading') }}
                    </h2>

                    @if ($classes->isNotEmpty())
                        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($classes as $class)
                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-center"
                                     data-aos="fade-up" data-aos-delay="{{ ($loop->index % 6) * 50 }}">
                                    <p class="font-display text-sm font-bold text-slate-900">{{ $class->name }}</p>
                                    @if ($class->stage)
                                        <p class="mt-0.5 text-[11px] uppercase tracking-wide text-slate-500">{{ $class->stage }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-6 text-sm text-slate-600">
                            {{ $t('classes_empty') }}
                        </p>
                    @endif

                    @if (! empty($next['body']))
                        <div class="mt-8 rounded-xl border-l-2 border-brand bg-white p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ $next['heading'] ?? 'What happens next' }}
                            </p>
                            <div class="mt-2 space-y-2 text-sm text-slate-600">
                                @foreach (preg_split('/\n\s*\n/', trim($next['body'])) as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{--
        School fees. Published only where the school has switched this on:
        section 11 says "where the school chooses to publish them", and the
        default is not to.
    --}}
    @if ($visibility->shows('fees') && $fees->isNotEmpty())
        <section class="section">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div class="max-w-2xl" data-aos="fade-up">
                    <p class="eyebrow">{{ $t('fees_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        {{ $t('fees_heading') }}
                    </h2>
                    <p class="mt-3 text-slate-600">
                        {{ $t('fees_note') }}
                    </p>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($fees as $structure)
                        <article class="card-soft p-7" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 80 }}">
                            <h3 class="font-display text-base font-bold text-slate-900">{{ $structure->name }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $structure->schoolClass?->name ?? 'All classes' }}
                            </p>

                            <p class="mt-4 font-display text-2xl font-bold tabular-nums text-slate-900">
                                {{ Money::format($structure->totalMinor()) }}
                            </p>

                            <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                                @foreach ($structure->items as $item)
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-slate-600">{{ $item->category }}</dt>
                                        <dd class="tabular-nums text-slate-700">{{ Money::format($item->amount_minor) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section section--ink">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="font-display text-2xl font-bold text-white sm:text-4xl" data-aos="fade-up">
                {{ $t('cta_heading') }}
            </h2>
            <p class="mt-4 text-white/75" data-aos="fade-up" data-aos-delay="80">
                {{ $t('cta_body') }}
            </p>

            <div class="mt-9 flex flex-wrap justify-center gap-3" data-aos="fade-up" data-aos-delay="160">
                <a href="{{ route('apply') }}"
                   class="press inline-flex items-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:bg-white/90">
                    {{ $t('cta_primary') }}
                </a>
                <a href="{{ route('public.contact') }}"
                   class="press inline-flex items-center rounded-lg border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    {{ $t('cta_secondary') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts.public>
