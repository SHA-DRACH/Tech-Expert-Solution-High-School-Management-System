@props(['trail' => []])

<nav aria-label="Breadcrumb" class="enter-fade mb-4">
    <ol class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
        @foreach ($trail as $label => $url)
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <span aria-hidden="true" class="text-slate-300">/</span>
                @endif

                @if ($loop->last || ! $url)
                    <span class="font-medium text-slate-700" @if ($loop->last) aria-current="page" @endif>{{ $label }}</span>
                @else
                    <a href="{{ $url }}" class="link-underline hover:text-slate-700">{{ $label }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
