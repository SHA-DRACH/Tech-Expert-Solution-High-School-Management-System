<x-layouts.app title="Audit trail" heading="Audit trail">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Audit trail' => null]" />

    <x-ui.page-header
        title="Audit trail"
        description="A permanent record of sensitive activity. Entries cannot be edited or removed."
    />

    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-56 flex-1">
                <label for="search" class="sr-only">Search the audit trail</label>
                <x-ui.input name="search" :value="$filters['search']" placeholder="Search by description or person…" />
            </div>

            <div class="w-48">
                <label for="module" class="sr-only">Filter by module</label>
                <x-ui.select
                    name="module"
                    :selected="$filters['module']"
                    placeholder="All modules"
                    :options="$modules->mapWithKeys(fn ($m) => [$m => $m])->all()"
                />
            </div>

            <div class="w-44">
                <label for="action" class="sr-only">Filter by action</label>
                <x-ui.select
                    name="action"
                    :selected="$filters['action']"
                    placeholder="All actions"
                    :options="$actions->mapWithKeys(fn ($a) => [$a => Str::headline($a)])->all()"
                />
            </div>

            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>

            @if (array_filter($filters))
                <x-ui.button :href="route('audit.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </form>

        @if ($logs->isEmpty())
            <x-ui.empty-state
                icon="☰"
                title="{{ array_filter($filters) ? 'No entries match those filters' : 'No activity recorded yet' }}"
                description="{{ array_filter($filters) ? 'Try widening the filters.' : 'Sensitive actions are recorded here automatically as people use the system.' }}"
            />
        @else
            <x-ui.table :headings="['When', 'Who', 'Action', 'Module', 'Details']">
                @foreach ($logs as $log)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                            <time datetime="{{ $log->created_at?->toIso8601String() }}">
                                {{ $log->created_at?->format('j M Y, H:i') }}
                            </time>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $log->user_name ?? 'System' }}</td>
                        <td class="px-5 py-3"><x-ui.badge>{{ Str::headline($log->action) }}</x-ui.badge></td>
                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $log->module }}</td>
                        <td class="px-5 py-3 text-slate-700">
                            <p>{{ $log->description }}</p>

                            @if ($log->old_values || $log->new_values)
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-xs text-slate-500 hover:text-slate-700">
                                        What changed
                                    </summary>
                                    <dl class="mt-2 space-y-1 rounded-lg bg-slate-50 p-3 text-xs">
                                        @foreach (($log->new_values ?? []) as $field => $value)
                                            <div class="flex flex-wrap gap-x-2">
                                                <dt class="font-medium text-slate-600">{{ Str::headline($field) }}:</dt>
                                                <dd class="text-slate-500">
                                                    @if (isset($log->old_values[$field]))
                                                        <s>{{ Str::limit(json_encode($log->old_values[$field]), 60) }}</s>
                                                        <span aria-hidden="true">→</span>
                                                    @endif
                                                    {{ Str::limit(json_encode($value), 60) }}
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </details>
                            @endif

                            @if ($log->ip_address)
                                <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $log->ip_address }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
