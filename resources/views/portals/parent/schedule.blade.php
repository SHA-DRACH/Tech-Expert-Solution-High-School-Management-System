<x-layouts.app title="Class schedule">
    <x-ui.page-header :title="$child->full_name.'\'s schedule'"
                      :description="($child->currentEnrollment?->section?->full_name ? $child->currentEnrollment->section->full_name.' · ' : '').'Every class this week: the time it starts and ends, the subject and the teacher.'">
        <x-slot:actions>
            @if ($week->isNotEmpty())
                <x-ui.button :href="route('parent.schedule.download', ['child' => $child->id])" variant="secondary">Download (Excel)</x-ui.button>
                <x-ui.button onclick="window.print()" variant="secondary">Print</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-portal.child-selector :children="$children" :selected="$child" route="parent.schedule" />

    <div class="printable">
        <x-ui.card>
            <x-schedule.week :week="$week" :days="$days"
                             empty="This class's timetable will appear here once the school publishes it." />
        </x-ui.card>
    </div>
</x-layouts.app>
