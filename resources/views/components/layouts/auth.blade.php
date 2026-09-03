@props(['title' => 'Sign in', 'school' => null])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $school?->name ?? config('app.name') }}</title>

    <style>
        :root {
            --brand-primary: {{ $school?->primary_color ?? '#1d4ed8' }};
            --brand-secondary: {{ $school?->secondary_color ?? '#0f766e' }};
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 font-sans text-slate-900 antialiased">
    <div class="w-full max-w-md">
        <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-3">
            @if ($school?->logo_path)
                <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt="" class="size-11 rounded-xl object-cover">
            @else
                <span class="grid size-11 place-items-center rounded-xl bg-brand text-lg font-bold text-white">
                    {{ $school?->initials() ?? 'G' }}
                </span>
            @endif
            <span>
                <strong class="block text-sm font-semibold">{{ $school?->name ?? 'Grace School Management System' }}</strong>
                <span class="block text-xs text-slate-500">{{ $school?->motto ?? 'School management platform' }}</span>
            </span>
        </a>

        {{ $slot }}

        <p class="mt-6 text-center text-xs text-slate-500">
            Powered by the Grace School Management System
            <span class="mt-0.5 block">Powered by Tech Expert Solution</span>
        </p>
    </div>
{{-- Pairs with the bundled Livewire ESM in app.js. Without it Livewire
     boots its own Alpine and ours starts a second one, which throws and
     takes every scroll-reveal on the page down with it. --}}
@livewireScriptConfig
</body>
</html>
