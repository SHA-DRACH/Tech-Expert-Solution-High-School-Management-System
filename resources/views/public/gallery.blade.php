@php $t = App\Support\SiteContent::for($page ?? null, 'gallery', $school); @endphp

<x-layouts.public :school="$school" :title="$t->title()">
    <section class="section--ink py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <p class="eyebrow eyebrow--onDark" data-aos="fade-up">{{ $t('eyebrow') }}</p>
            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl" data-aos="fade-up" data-aos-delay="80">
                {{ $t->title() }}
            </h1>
            @if ($t->summary() !== '')
                <p class="mt-4 max-w-2xl text-white/75" data-aos="fade-up" data-aos-delay="160">{{ $t->summary() }}</p>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        @if ($albums->isEmpty())
            <p class="py-16 text-center text-slate-500">{{ $t('empty') }}</p>
        @else
            @foreach ($albums as $album => $items)
                <div class="mb-12">
                    <h2 class="mb-5 font-display text-xl font-bold text-slate-900">{{ $album ?: 'General' }}</h2>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($items as $item)
                            <figure class="zoom-frame overflow-hidden rounded-xl border border-slate-200"
                                    data-aos="fade-up" data-aos-delay="{{ ($loop->index % 4) * 60 }}">
                                <img src="{{ Storage::disk('public')->url($item->image_path) }}"
                                     alt="{{ $item->caption ?? $item->title ?? 'School photograph' }}"
                                     loading="lazy" class="aspect-square w-full object-cover">
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </section>
</x-layouts.public>
