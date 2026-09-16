<x-layouts.app title="My timetable">
    <x-ui.page-header title="My timetable"
                      :description="($student->currentEnrollment?->section?->full_name ? $student->currentEnrollment->section->full_name.' · ' : '').'Your weekly class schedule.'">
        <x-slot:actions>
            @if ($week->isNotEmpty())
                @if ($canDownload)
                    <x-ui.button :href="route('student.timetable.download')" variant="secondary">Download (Excel)</x-ui.button>
                @endif
                <x-ui.button onclick="window.print()" variant="secondary">Print</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="printable">
        <x-ui.card>
            <x-schedule.week :week="$week" :days="$days"
                             empty="Your class timetable will appear here once it is published." />
        </x-ui.card>
    </div>
</x-layouts.app>
