@props(['school', 'title' => null, 'socialLinks' => null])

@php
    use App\Services\PublicVisibility;

    $visibility = app(PublicVisibility::class);
    $socialLinks = $socialLinks ?? collect();

    /*
     | The menu is the school's own (Website › Menu & footer). SiteMenu drops any
     | section the school has chosen not to publish, so no dead link is left.
     | A link counts as current when its route matches, or - for pages and
     | addresses without a route - when it points at the page being viewed.
     */
    $navigation = App\Support\SiteMenu::links($visibility)->map(function (array $item) {
        $item['current'] = $item['active']
            ? request()->routeIs($item['active'])
            : rtrim($item['url'], '/') === rtrim(request()->url(), '/');

        return $item;
    });

    $footerText = App\Support\SiteMenu::footerText();
@endphp

<!DOCTYPE html>
{{--
    Note the height classes below. The body must NOT have a fixed `h-full`:
    combined with `overflow-x: clip` from the stylesheet, a constrained height
    turns the body itself into the vertical scroll container, the window stops
    scrolling, and every scroll-reveal on the page stays invisible. `min-h-screen`
    keeps the footer at the bottom of short pages without that side effect.
--}}
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ $school->name }}</title>
    <meta name="description" content="{{ $school->motto }}">

    @if ($school->favicon_path)
        <link rel="icon" href="{{ Storage::disk('public')->url($school->favicon_path) }}">
    @endif

    <style>
        :root {
            --brand-primary: {{ $school->primary_color }};
            --brand-secondary: {{ $school->secondary_color }};
        }
    </style>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-slate-900 antialiased">
@include('partials.splash')

<a href="#main" class="skip-link">Skip to content</a>

{{-- Contact strip: the details families most often need, above everything. --}}
<div class="hidden bg-slate-950 text-white/75 lg:block">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-2 text-xs sm:px-6">
        <div class="flex flex-wrap items-center gap-5">
            @if ($school->phone)
                <a href="tel:{{ $school->phone }}" class="flex items-center gap-1.5 transition-colors hover:text-white">
                    <span aria-hidden="true">✆</span>{{ $school->phone }}
                </a>
            @endif
            @if ($school->email)
                <a href="mailto:{{ $school->email }}" class="flex items-center gap-1.5 transition-colors hover:text-white">
                    <span aria-hidden="true">✉</span>{{ $school->email }}
                </a>
            @endif
            @if ($school->address)
                <span class="flex items-center gap-1.5"><span aria-hidden="true">◎</span>{{ $school->address }}</span>
            @endif
        </div>

        @if ($socialLinks->isNotEmpty())
            <div class="flex items-center gap-3">
                @foreach ($socialLinks as $link)
                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                       class="transition-colors hover:text-white">{{ $link->platform }}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<header x-data="{ open: false }" class="site-header sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
        <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-3">
            @if ($school->logo_path)
                <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt=""
                     class="size-11 rounded-lg object-cover transition-transform duration-300 group-hover:scale-105">
            @else
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-brand font-display text-base font-bold text-white">
                    {{ $school->initials() }}
                </span>
            @endif

            <span class="min-w-0">
                <strong class="block truncate font-display text-sm font-bold sm:text-base">
                    {{ $school->short_name ?? $school->name }}
                </strong>
                <span class="hidden truncate text-xs text-slate-500 sm:block">{{ $school->motto }}</span>
            </span>
        </a>

        <nav class="hidden items-center gap-0.5 text-sm xl:flex" aria-label="Main">
            @foreach ($navigation as $item)
                @php $isActive = $item['current']; @endphp

                <a href="{{ $item['url'] }}" @if ($item['external']) target="_blank" rel="noopener noreferrer" @endif
                   class="group relative whitespace-nowrap rounded-lg px-2.5 py-2 font-medium transition-colors
                          {{ $isActive ? 'text-brand' : 'text-slate-600 hover:text-brand' }}"
                   @if ($isActive) aria-current="page" @endif>
                    {{ $item['label'] }}

                    <span aria-hidden="true"
                          class="absolute inset-x-3 bottom-1 h-0.5 origin-center rounded-full bg-brand transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]
                                 {{ $isActive ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100' }}"></span>
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <x-ui.button :href="route('login')" variant="secondary" size="sm" class="hidden sm:inline-flex">
                Login
            </x-ui.button>

            <x-ui.button :href="route('apply')" size="sm" class="hidden sm:inline-flex">Apply</x-ui.button>

            <button
                type="button"
                @click="open = ! open"
                class="press grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600 xl:hidden"
                aria-label="Toggle navigation"
                :aria-expanded="open"
            >
                <span x-show="! open" aria-hidden="true">☰</span>
                <span x-show="open" x-cloak aria-hidden="true">✕</span>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-collapse class="border-t border-slate-200 xl:hidden">
        <nav class="mx-auto max-w-7xl space-y-0.5 px-4 py-3 text-sm" aria-label="Mobile">
            @foreach ($navigation as $item)
                <a href="{{ $item['url'] }}"
                   @class([
                       'block rounded-lg px-3 py-2.5 font-medium transition-colors',
                       'bg-brand/8 text-brand' => $item['current'],
                       'text-slate-700 hover:bg-slate-50' => ! $item['current'],
                   ])>{{ $item['label'] }}</a>
            @endforeach

            <div class="mt-2 grid gap-2 border-t border-slate-100 pt-3 sm:hidden">
                <x-ui.button :href="route('login')" variant="secondary">Login</x-ui.button>
                <x-ui.button :href="route('apply')">Apply for admission</x-ui.button>
            </div>
        </nav>
    </div>
</header>

<main id="main" class="flex-1">
    {{ $slot }}
</main>

<footer class="mt-auto border-t border-slate-200 bg-slate-950 text-white/70">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <div class="flex items-center gap-3">
                @if ($school->logo_path)
                    <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt=""
                         class="size-11 rounded-lg object-cover">
                @else
                    <span class="grid size-11 place-items-center rounded-lg bg-brand font-display text-base font-bold text-white">
                        {{ $school->initials() }}
                    </span>
                @endif

                <p class="font-display text-base font-bold text-white">{{ $school->name }}</p>
            </div>

            <p class="mt-4 max-w-sm whitespace-pre-line text-sm">{{ $footerText ?? $school->motto }}</p>

            @if ($socialLinks->isNotEmpty())
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($socialLinks as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                           class="rounded-lg border border-white/15 px-3 py-1.5 text-xs transition-colors hover:border-white/40 hover:text-white">
                            {{ $link->platform }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-white/50">Explore</p>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($navigation->take(6) as $item)
                    <li><a href="{{ $item['url'] }}" class="link-underline transition-colors hover:text-white">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-white/50">Contact</p>
            <address class="mt-4 space-y-2 text-sm not-italic">
                @if ($school->address)<p>{{ $school->address }}</p>@endif
                @if ($school->phone)
                    <p><a href="tel:{{ $school->phone }}" class="link-underline hover:text-white">{{ $school->phone }}</a></p>
                @endif
                @if ($school->email)
                    <p><a href="mailto:{{ $school->email }}" class="link-underline hover:text-white">{{ $school->email }}</a></p>
                @endif
            </address>

            <div class="mt-5 grid gap-2">
                <x-ui.button :href="route('apply')" size="sm">Apply for admission</x-ui.button>
                <x-ui.button :href="route('login')" variant="secondary" size="sm">Parent &amp; student login</x-ui.button>
                <x-ui.button :href="route('online.index')" variant="secondary" size="sm">Online services</x-ui.button>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10">
        <p class="mx-auto max-w-7xl px-4 py-5 text-xs text-white/50 sm:px-6">
            &copy; {{ now()->year }} {{ $school->name }}. Powered by {{ config('app.product', 'NovaxSuites') }}.
            Built by Shadrach Jimice Jr.
        </p>
    </div>
</footer>

{{-- Pairs with the bundled Livewire ESM in app.js. Without it Livewire
     boots its own Alpine and ours starts a second one, which throws and
     takes every scroll-reveal on the page down with it. --}}
@livewireScriptConfig
</body>
</html>
