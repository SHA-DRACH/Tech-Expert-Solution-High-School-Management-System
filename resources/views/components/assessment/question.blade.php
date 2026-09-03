@props(['assessment'])

{{--
    The question itself, as set by the teacher.

    Used wherever a student or a parent looks at a piece of work. Rendered only
    when there is something to show, so an assessment with no question does not
    leave an empty panel implying one is missing.
--}}
@if (filled($assessment->instructions) || $assessment->attachment_path)
    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">The question</p>

        @if (filled($assessment->instructions))
            <div class="mt-2 space-y-2 text-sm text-slate-700">
                @foreach (preg_split('/\n\s*\n/', trim($assessment->instructions)) as $paragraph)
                    <p class="whitespace-pre-line">{{ $paragraph }}</p>
                @endforeach
            </div>
        @endif

        @if ($assessment->attachment_path)
            <a href="{{ route('assessments.question', $assessment) }}"
               class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-brand transition hover:border-brand/40">
                <span aria-hidden="true">🗎</span>
                {{ $assessment->attachment_name ?: 'Question paper' }}
            </a>
        @endif
    </div>
@endif
