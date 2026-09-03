<x-layouts.public :school="$school" title="News">
    <section class="section--ink py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <p class="eyebrow eyebrow--onDark" data-aos="fade-up">Latest from the school</p>
            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl" data-aos="fade-up" data-aos-delay="80">
                News
            </h1>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        @if ($posts->isEmpty())
            <p class="py-16 text-center text-slate-500">There is no news to show just yet.</p>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="card-soft overflow-hidden" data-aos="fade-up" data-aos-delay="{{ ($loop->index % 3) * 80 }}">
                        @if ($post->image_path)
                            <div class="zoom-frame aspect-[16/10]">
                                <img src="{{ Storage::disk('public')->url($post->image_path) }}" alt="" loading="lazy">
                            </div>
                        @endif

                        <div class="p-6">
                            <time datetime="{{ $post->published_at?->toDateString() }}" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                {{ $post->published_at?->format('j F Y') }}
                            </time>

                            <h2 class="mt-2 font-display text-lg font-bold text-slate-900">
                                <a href="{{ route('public.news.show', $post->slug) }}" class="link-underline">
                                    {{ $post->title }}
                                </a>
                            </h2>

                            <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $post->excerpt }}</p>

                            <a href="{{ route('public.news.show', $post->slug) }}"
                               class="group mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                                Read more
                                <span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">&rarr;</span>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.public>
