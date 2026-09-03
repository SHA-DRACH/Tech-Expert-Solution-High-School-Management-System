<x-layouts.app title="Student portal permissions" heading="Student portal permissions">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Students' => route('students.index'), 'Portal permissions' => null]" />

    <x-ui.page-header
        title="Student portal permissions"
        description="What student accounts may open. These are the school-wide defaults; any student can be given their own settings."
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" title="School-wide defaults" description="Applies to every student without their own override.">
            <form method="POST" action="{{ route('students.permissions.defaults') }}" class="space-y-1">
                @csrf
                @method('PUT')

                @foreach ($abilities as $ability => $definition)
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-800">{{ $definition['label'] }}</span>
                            <span class="block font-mono text-[11px] text-slate-400">{{ $ability }}</span>
                        </span>

                        {{-- A switch reads more clearly than a checkbox for yes/no access. --}}
                        <span class="relative inline-flex shrink-0">
                            <input type="checkbox" name="abilities[{{ $ability }}]" value="1"
                                   class="peer sr-only" @checked($defaults[$ability])>
                            <span class="block h-6 w-11 rounded-full bg-slate-200 transition-colors duration-200 peer-checked:bg-brand"></span>
                            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                @endforeach

                <div class="flex justify-end pt-4">
                    <x-ui.button type="submit">Save defaults</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Students with their own settings" :padded="false">
            @forelse ($overridden as $student)
                <a href="{{ route('students.permissions.edit', $student) }}"
                   class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 transition-colors last:border-0 hover:bg-slate-50">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $student->full_name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ $student->student_number }}</p>
                    </div>
                    <x-ui.badge tone="info">{{ $student->permissions->count() }} custom</x-ui.badge>
                </a>
            @empty
                <x-ui.empty-state icon="◌" title="No overrides"
                                  description="Every student currently follows the school defaults." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
