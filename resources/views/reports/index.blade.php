@php use App\Support\Money; @endphp

<x-layouts.app title="Reports" heading="Reports">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Reports' => null]" />

    <x-ui.page-header title="Reports" description="Enrollment, academic, attendance, finance and admissions summaries.">
        <x-slot:actions>
            <x-ui.button onclick="window.print()" variant="secondary">Print</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Enrollment by class" description="Students placed in each grade level">
            @if ($enrollmentByClass->isEmpty())
                <x-ui.empty-state icon="◉" title="No classes yet" />
            @else
                <x-ui.bar-chart
                    :series="$enrollmentByClass->map(fn ($c) => ['label' => $c->name, 'value' => $c->enrollments_count])->all()"
                    aria-label="Students enrolled in each class"
                />
            @endif
        </x-ui.card>

        <x-ui.card title="Students by status">
            <div class="space-y-4">
                @php $studentTotal = max($studentsByStatus->sum(), 1); @endphp

                @forelse ($studentsByStatus as $status => $count)
                    <x-ui.progress
                        :value="round($count / $studentTotal * 100, 1)"
                        :label="Str::headline($status)"
                        :caption="$count.' students'"
                        :tone="$status === 'active' ? 'success' : 'brand'"
                    />
                @empty
                    <x-ui.empty-state icon="◉" title="No students yet" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card title="Attendance (last 30 days)">
            <div class="space-y-4">
                @php $attendanceTotal = max($attendanceByStatus->sum(), 1); @endphp

                @forelse ($attendanceByStatus as $status => $count)
                    <x-ui.progress
                        :value="round($count / $attendanceTotal * 100, 1)"
                        :label="Str::headline($status)"
                        :caption="$count.' marks'"
                        :tone="in_array($status, ['present', 'late']) ? 'success' : 'warning'"
                    />
                @empty
                    <x-ui.empty-state icon="◷" title="Nothing recorded" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card title="Admissions by stage">
            <div class="space-y-4">
                @php $admissionTotal = max($admissionsByStatus->sum(), 1); @endphp

                @forelse ($admissionsByStatus as $status => $count)
                    <x-ui.progress
                        :value="round($count / $admissionTotal * 100, 1)"
                        :label="Str::headline($status)"
                        :caption="$count.' applications'"
                    />
                @empty
                    <x-ui.empty-state icon="✦" title="No applications yet" />
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" title="Finance summary">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total invoiced</p>
                    <p class="mt-1 font-display text-xl font-bold tabular-nums text-slate-900">{{ Money::format($invoicedMinor) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Collected</p>
                    <p class="mt-1 font-display text-xl font-bold tabular-nums text-emerald-800">{{ Money::format($collectedMinor) }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Outstanding</p>
                    <p class="mt-1 font-display text-xl font-bold tabular-nums text-amber-800">{{ Money::format($outstandingMinor) }}</p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" title="Students by gender">
            <div class="grid gap-4 sm:grid-cols-3">
                @forelse ($studentsByGender as $gender => $count)
                    <div class="rounded-lg border border-slate-200 p-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $gender ?: 'Not stated' }}</p>
                        <p class="mt-1 font-display text-xl font-bold tabular-nums text-slate-900">{{ $count }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No student records yet.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
