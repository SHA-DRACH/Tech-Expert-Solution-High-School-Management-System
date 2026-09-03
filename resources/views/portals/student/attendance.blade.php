<x-layouts.app title="My attendance">
    <x-ui.page-header title="My attendance" :description="$rate === null ? 'Nothing recorded yet' : $rate.'% present across the year'" />

    <x-ui.card :padded="false">
        @if ($records->isEmpty())
            <x-ui.empty-state icon="◷" title="Nothing recorded yet" description="Your attendance will appear here." />
        @else
            <x-ui.table :headings="['Date', 'Status', 'Note']">
                @foreach ($records as $record)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $record->recorded_on->format('l, j M Y') }}</td>
                        <td class="px-5 py-3"><x-ui.status-badge :status="$record->status" /></td>
                        <td class="px-5 py-3 text-slate-600">{{ $record->remark ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div class="border-t border-slate-100 px-5 py-3">{{ $records->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
