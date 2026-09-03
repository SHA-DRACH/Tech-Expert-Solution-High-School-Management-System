@php
    $columns = array_merge($definition['required'], $definition['optional']);
    $sample = implode(',', $columns);
@endphp

<x-layouts.app :title="'Import '.$definition['label']" heading="Import from CSV">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        $definition['label'] => route($definition['back']),
        'Import' => null,
    ]" />

    <x-ui.page-header
        :title="'Import '.Str::lower($definition['label'])"
        description="Upload a spreadsheet to see exactly what would be created. Nothing is saved until you confirm."
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-6">
            <x-ui.card title="Choose a file" description="A CSV file, up to 5 MB, with the column headings in the first row.">
                <form method="POST" action="{{ route('imports.preview', $type) }}" enctype="multipart/form-data">
                    @csrf

                    <x-ui.field label="CSV file" name="file">
                        <input type="file" name="file" id="file" accept=".csv,text/csv"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </x-ui.field>

                    <div class="mt-5">
                        <x-ui.button type="submit">Check the file</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            @if ($preview)
                @php
                    $valid = collect($preview['valid']);
                    $problems = collect($preview['problems']);
                @endphp

                <x-ui.card
                    title="What would happen"
                    :description="$valid->count().' '.Str::plural('row', $valid->count()).' ready, '.$problems->count().' '.Str::plural('problem', $problems->count()).'.'"
                >
                    @if ($valid->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-max border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-left">
                                        <th scope="col" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Line</th>
                                        <th scope="col" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                                        <th scope="col" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                                        <th scope="col" class="py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Capped: the point is to recognise the file, not read all of it. --}}
                                    @foreach ($valid->take(15) as $row)
                                        <tr class="border-b border-slate-100">
                                            <td class="py-2 pr-4 tabular-nums text-slate-400">{{ $row['__line'] ?? '' }}</td>
                                            <td class="py-2 pr-4 font-medium text-slate-900">
                                                {{ trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) }}
                                            </td>
                                            <td class="py-2 pr-4 text-slate-600">{{ $row['email'] ?? '' }}</td>
                                            <td class="py-2 text-slate-600">{{ $row['department'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($valid->count() > 15)
                            <p class="mt-3 text-xs text-slate-500">
                                and {{ $valid->count() - 15 }} more {{ Str::plural('row', $valid->count() - 15) }}.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('imports.store', $type) }}"
                              class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-5">
                            @csrf
                            <x-ui.button type="submit">
                                Import {{ $valid->count() }} {{ Str::plural('record', $valid->count()) }}
                            </x-ui.button>
                            <span class="text-xs text-slate-500">Imported staff are not published to the website.</span>
                        </form>
                    @else
                        <p class="text-sm text-slate-500">No row in that file can be imported. The problems are listed below.</p>
                    @endif
                </x-ui.card>

                @if ($problems->isNotEmpty())
                    <x-ui.card
                        title="Rows that would be skipped"
                        description="Fix these in your spreadsheet and upload it again. Everything else can still be imported now."
                    >
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($problems as $problem)
                                <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2.5">
                                    <span class="tabular-nums text-xs font-semibold text-slate-400">Line {{ $problem['line'] }}</span>
                                    <span class="font-medium text-slate-800">{{ $problem['name'] }}</span>
                                    <span class="text-rose-700">{{ $problem['reason'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            @endif
        </div>

        <div class="space-y-5">
            <x-ui.card title="Columns">
                <p class="text-sm text-slate-600">
                    The first row must be the headings. Capitals, spaces and underscores are all treated the
                    same, so <span class="font-mono text-xs">First Name</span> and
                    <span class="font-mono text-xs">first_name</span> both work.
                </p>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Required</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($definition['required'] as $column)
                        <span class="rounded-md bg-brand/8 px-2 py-1 font-mono text-xs text-brand">{{ $column }}</span>
                    @endforeach
                </div>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Optional</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($definition['optional'] as $column)
                        <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600">{{ $column }}</span>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-slate-500">
                    Any other column is ignored. A department that does not already exist is left unset rather
                    than created, so a typo cannot invent one.
                </p>
            </x-ui.card>

            <x-ui.card title="Starting from your own data">
                <p class="text-sm text-slate-600">
                    The CSV export uses these same column names, so you can export what is already here, edit it
                    in a spreadsheet, and import it back.
                </p>

                <div class="mt-4">
                    <x-ui.button :href="route('exports.teachers')" variant="secondary" size="sm">Export current staff</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
