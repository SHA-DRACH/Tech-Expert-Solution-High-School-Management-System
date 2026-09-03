<x-layouts.app :title="$guardian->full_name" :heading="$guardian->full_name">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Parents & guardians' => route('guardians.index'), $guardian->full_name => null]" />

    <x-ui.page-header :title="$guardian->full_name" :description="$guardian->occupation">
        <x-slot:actions>
            <x-ui.status-badge :status="$guardian->status" />

            @can('update', $guardian)
                <x-ui.button :href="route('guardians.edit', $guardian)">Edit record</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="Contact details">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ([
                    'Phone' => $guardian->phone,
                    'Email' => $guardian->email,
                    'Occupation' => $guardian->occupation,
                    'Portal account' => $guardian->user?->email,
                    'Address' => $guardian->address,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Children" :padded="false">
            @forelse ($guardian->students as $student)
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
                    <div class="min-w-0">
                        @can('view', $student)
                            <a href="{{ route('students.show', $student) }}" class="truncate text-sm font-medium hover:text-brand hover:underline">
                                {{ $student->full_name }}
                            </a>
                        @else
                            <p class="truncate text-sm font-medium">{{ $student->full_name }}</p>
                        @endcan
                        <p class="font-mono text-xs text-slate-500">{{ $student->student_number }}</p>
                    </div>
                    <x-ui.status-badge :status="$student->status" />
                </div>
            @empty
                <x-ui.empty-state icon="◉" title="No children linked" description="Link a student to this guardian record." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
