@props(['headings' => []])

{{-- Wide tables scroll inside their own container so the page never does. --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        @if ($headings)
            <thead>
                <tr class="text-left">
                    @foreach ($headings as $heading)
                        <th scope="col" class="whitespace-nowrap bg-slate-50/60 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ $heading }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-slate-100 [&>tr]:transition-colors [&>tr]:duration-150 [&>tr:hover]:bg-slate-50">
            {{ $slot }}
        </tbody>
    </table>
</div>
