<x-layouts.public :school="$school" title="Admissions" :social-links="$socialLinks">
    <section class="section--ink py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <nav aria-label="Breadcrumb" data-aos="fade-up">
                <ol class="flex items-center gap-2 text-xs text-white/60">
                    <li><a href="{{ route('home') }}" class="link-underline hover:text-white">Home</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="font-medium text-white/90" aria-current="page">Admissions</li>
                </ol>
            </nav>

            <h1 class="mt-4 font-display text-3xl font-bold text-white sm:text-4xl" data-aos="fade-up" data-aos-delay="80">
                Applications are closed
            </h1>
        </div>
    </section>

    <section class="section">
        <div class="mx-auto max-w-2xl px-4 text-center sm:px-6">
            <span class="icon-badge mx-auto" aria-hidden="true">◷</span>

            <p class="mt-6 text-lg text-slate-700" data-aos="fade-up">{{ $message }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-3" data-aos="fade-up" data-aos-delay="80">
                <x-ui.button :href="route('public.admissions')" variant="secondary">Admissions information</x-ui.button>
                <x-ui.button :href="route('public.contact')">Contact the office</x-ui.button>
            </div>
        </div>
    </section>
</x-layouts.public>
