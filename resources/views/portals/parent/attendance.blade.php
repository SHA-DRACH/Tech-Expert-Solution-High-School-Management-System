@php
    use App\Support\Money;

@endphp
<x-layouts.app title="Attendance">
    <x-ui.page-header title="Attendance" :description="$child->full_name" />

    <x-portal.child-selector :children="$children" :selected="$child" route="parent.attendance" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Attendance rate" :value="$rate === null ? 'Not recorded' : $rate.'%'" note="Present or late" />
        <x-ui.stat label="Present" :value="(string) $breakdown['present']" note="Days marked present" />
        <x-ui.stat label="Absent" :value="(string) $breakdown['absent']" note="Days missed" />
        <x-ui.stat label="Late" :value="(string) $breakdown['late']" note="Late arrivals" />
    </div>

    <x-ui.card class="mt-6" title="Attendance history" :padded="false">
        @if ($records->isEmpty())
            <x-ui.empty-state icon="◷" title="Nothing recorded yet" description="Daily attendance will appear here once teachers record it." />
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
