<x-layouts.public :school="$school" :title="$post->title">
    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <a href="{{ route('public.news') }}" class="text-sm font-medium text-brand hover:underline">&larr; All news</a>

        <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
            {{ $post->title }}
        </h1>

        <p class="mt-3 text-sm text-slate-500">
            {{ $post->published_at?->format('j F Y') }}
            @if ($post->author) · {{ $post->author->name }} @endif
        </p>

        @if ($post->image_path)
            <img src="{{ Storage::disk('public')->url($post->image_path) }}" alt=""
                 class="mt-8 w-full rounded-xl object-cover">
        @endif

        <div class="prose prose-slate mt-8 max-w-none">
            @foreach (preg_split('/\n\s*\n/', $post->body) as $paragraph)
                <p class="mb-4 text-slate-700">{{ $paragraph }}</p>
            @endforeach
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
                <h2 class="font-display text-xl font-bold text-slate-900">More news</h2>

                <div class="mt-6 grid gap-6 md:grid-cols-3">
                    @foreach ($related as $other)
                        <article class="card-soft p-5" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                            <time class="text-xs text-slate-500">{{ $other->published_at?->format('j M Y') }}</time>
                            <h3 class="mt-1 font-display text-base font-bold text-slate-900">
                                <a href="{{ route('public.news.show', $other->slug) }}" class="link-underline">{{ $other->title }}</a>
                            </h3>
                            <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $other->excerpt }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.public>
