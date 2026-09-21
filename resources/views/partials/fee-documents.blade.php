{{--
    Fee structure PDFs a family may download.

    $documents  collection of FeeStructure with a document
    $public     true on the public website (uses the public download route)
    $heading    optional heading
--}}
@php
    $public = $public ?? false;
@endphp

@if ($documents->isNotEmpty())
    <div class="mt-4">
        @if (! empty($heading))
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</p>
        @endif

        <ul class="space-y-2">
            @foreach ($documents as $structure)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-xs font-bold text-rose-700" aria-hidden="true">PDF</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $structure->name }}</p>
                            <p class="truncate text-xs text-slate-500">
                                {{ $structure->schoolClass?->name ?? 'All classes' }} · {{ $structure->term?->name ?? 'Whole year' }}
                            </p>
                        </div>
                    </div>

                    <a href="{{ $public ? route('online.fees.download', $structure) : route('fees.document.download', $structure) }}"
                       class="press inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-2 text-xs font-semibold text-white transition hover:brightness-110">
                        <span aria-hidden="true">⬇</span> Download
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
