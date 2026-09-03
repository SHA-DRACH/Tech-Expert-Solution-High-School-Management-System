<x-layouts.app :title="$teacher->full_name" :heading="$teacher->full_name">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Teachers & staff' => route('teachers.index'), $teacher->full_name => null]" />

    <x-ui.page-header :title="$teacher->full_name" :description="$teacher->staff_number">
        <x-slot:actions>
            @if ($teacher->is_public)
                <x-ui.badge tone="info">Published</x-ui.badge>
            @endif
            <x-ui.status-badge :status="$teacher->status" />

            @can('teachers.update')
                <x-ui.button :href="route('teachers.edit', $teacher)">Edit record</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Personal & employment">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        'Staff number' => $teacher->staff_number,
                        'Department' => $teacher->department?->name,
                        'Gender' => $teacher->gender,
                        'Date of birth' => $teacher->date_of_birth?->format('j F Y'),
                        'Phone' => $teacher->phone,
                        'Email' => $teacher->email,
                        'Employment type' => $teacher->employment_type,
                        'Date hired' => $teacher->hired_on?->format('j F Y'),
                        'Experience' => $teacher->experience_years ? $teacher->experience_years.' years' : null,
                        'Portal account' => $teacher->user?->email,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($teacher->biography)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Biography</dt>
                        <dd class="mt-1 text-sm text-slate-700">{{ $teacher->biography }}</dd>
                    </div>
                @endif
            </x-ui.card>

            {{--
                Adding a teacher creates the person, not an account. Offering
                the account here is the difference between a teacher who can
                sign in and one whose record looks complete but who has no way
                into the system at all.
            --}}
            @if ($teacher->user_id === null && $roles->isNotEmpty())
                <x-ui.card
                    title="This member of staff cannot sign in"
                    description="They have a staff record but no account. Create one and give them the password yourself."
                >
                    @error('login')
                        <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $message }}</p>
                    @enderror

                    <form method="POST" action="{{ route('teachers.login.store', $teacher) }}" class="space-y-5">
                        @csrf

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.field label="Email address" name="email" required
                                        hint="What they sign in with.">
                                <x-ui.input name="email" type="email" :value="$teacher->email" required />
                            </x-ui.field>

                            <x-ui.field label="Role" name="role_id" required
                                        hint="Teacher is the usual choice; it opens the teaching workspace.">
                                <x-ui.select
                                    name="role_id"
                                    placeholder="Select a role"
                                    :selected="$roles->firstWhere('slug', 'teacher')?->id"
                                    :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->name])->all()"
                                />
                            </x-ui.field>

                            <x-ui.field label="Password" name="password" required hint="At least 12 characters.">
                                <x-ui.input name="password" type="password" autocomplete="new-password" :remember="false" required />
                            </x-ui.field>

                            <x-ui.field label="Confirm password" name="password_confirmation" required>
                                <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" :remember="false" required />
                            </x-ui.field>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <p class="text-xs text-slate-500">
                                The password is not shown again — pass it on, and ask them to change it
                                from <span class="font-medium">My account</span> once they are in.
                            </p>
                            <x-ui.button type="submit">Create login</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            <x-ui.card title="Qualifications & certifications" :padded="false">
                @forelse ($teacher->qualifications as $qualification)
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-3.5 last:border-0">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900">{{ $qualification->title }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $qualification->institution }}@if ($qualification->awarded_year) · {{ $qualification->awarded_year }}@endif
                                @if ($qualification->authority) · {{ $qualification->authority }}@endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-ui.badge>{{ Str::headline($qualification->type) }}</x-ui.badge>
                            @if ($qualification->is_public)
                                <x-ui.badge tone="info">Public</x-ui.badge>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="◈" title="Nothing recorded" description="Add the qualifications this teacher holds." />
                @endforelse

                @can('teachers.update')
                    <form method="POST" action="{{ route('teachers.qualifications.store', $teacher) }}"
                          class="border-t border-slate-100 px-5 py-4">
                        @csrf

                        <p class="mb-3 text-sm font-semibold text-slate-800">Add a qualification</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.field label="Type" name="type" required>
                                <x-ui.select name="type"
                                             :options="collect($qualificationTypes)->mapWithKeys(fn ($t) => [$t => Str::headline($t)])->all()" />
                            </x-ui.field>

                            <x-ui.field label="Title" name="title" required>
                                <x-ui.input name="title" required placeholder="BSc Mathematics" />
                            </x-ui.field>

                            <x-ui.field label="Institution" name="institution">
                                <x-ui.input name="institution" />
                            </x-ui.field>

                            <x-ui.field label="Year awarded" name="awarded_year">
                                <x-ui.input name="awarded_year" type="number" min="1950" max="{{ now()->year + 1 }}" />
                            </x-ui.field>
                        </div>

                        <label class="mt-3 flex items-center gap-2">
                            <input type="checkbox" name="is_public" value="1" checked
                                   class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                            <span class="text-sm text-slate-600">Show this publicly</span>
                        </label>

                        <div class="mt-4">
                            <x-ui.button type="submit" variant="secondary" size="sm">Add qualification</x-ui.button>
                        </div>
                    </form>
                @endcan
            </x-ui.card>
        </div>

        <x-ui.card title="Teaching assignments" :padded="false">
            @forelse ($teacher->teachingAssignments as $assignment)
                <div class="border-b border-slate-100 px-5 py-3 last:border-0">
                    <p class="text-sm font-medium text-slate-900">{{ $assignment->subject?->name }}</p>
                    <p class="text-xs text-slate-500">{{ $assignment->section?->full_name }}</p>
                </div>
            @empty
                <x-ui.empty-state icon="◉" title="No classes assigned" description="Assign classes and subjects from Academics." />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
