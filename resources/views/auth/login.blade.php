@php $school = app(App\Support\SchoolContext::class)->school(); @endphp

<x-layouts.auth title="Sign in" :school="$school">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-lg font-semibold tracking-tight">Sign in to your account</h1>
        <p class="mt-1 text-sm text-slate-500">Parents, students and staff use the same sign-in.</p>

        @if ($errors->any())
            <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf

            <x-ui.field label="Email address" name="email" required>
                <x-ui.input name="email" type="email" autocomplete="username" required autofocus placeholder="you@school.edu.lr" />
            </x-ui.field>

            <x-ui.field label="Password" name="password" required>
                <x-ui.input name="password" type="password" autocomplete="current-password" required placeholder="••••••••••••" />
            </x-ui.field>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="remember" id="remember" value="1"
                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                <label for="remember" class="text-sm text-slate-600">Keep me signed in</label>
            </div>

            <x-ui.button type="submit" class="w-full">Sign in</x-ui.button>
        </form>
    </div>

    <p class="mt-5 text-center text-sm text-slate-600">
        New to {{ $school?->short_name ?? 'the school' }}?
        <a href="{{ route('apply') }}" class="font-medium text-brand hover:underline">Apply for admission</a>
    </p>
</x-layouts.auth>
