<x-layouts.public :school="$school" title="Events">
    <section class="section--ink py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <p class="eyebrow eyebrow--onDark" data-aos="fade-up">What is coming up</p>
            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl" data-aos="fade-up" data-aos-delay="80">
                Events
            </h1>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
        @if ($events->isEmpty())
            <p class="py-16 text-center text-slate-500">There are no events on the calendar right now.</p>
        @else
            <div class="space-y-4">
                @foreach ($events as $event)
                    <article class="card-soft flex items-start gap-5 p-6"
                             data-aos="fade-up" data-aos-delay="{{ ($loop->index % 6) * 60 }}">
                        <div class="grid size-16 shrink-0 place-items-center rounded-xl bg-brand/8 text-center">
                            <span class="block font-display text-xl font-bold leading-none text-brand">
                                {{ $event->starts_at->format('j') }}
                            </span>
                            <span class="mt-0.5 block text-[11px] uppercase tracking-wide text-brand/70">
                                {{ $event->starts_at->format('M') }}
                            </span>
                        </div>

                        <div class="min-w-0">
                            <h2 class="font-display text-lg font-bold text-slate-900">{{ $event->title }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $event->starts_at->format('l, j F Y \a\t H:i') }}
                                @if ($event->location) · {{ $event->location }} @endif
                            </p>
                            @if ($event->description)
                                <p class="mt-2 text-sm text-slate-600">{{ $event->description }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.public>
