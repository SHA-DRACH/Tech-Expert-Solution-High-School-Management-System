<x-layouts.app title="Create account" heading="Create account">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Users & staff' => route('users.index'), 'Create account' => null]" />

    <x-ui.page-header
        title="Create a user account"
        description="Choose a role, then link the account to the person it represents."
    />

    <form method="POST" action="{{ route('users.store') }}" class="max-w-3xl space-y-6" x-data="{ role: '{{ old('role_id') }}' }">
        @csrf

        <x-ui.card title="Account details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Full name" name="name" required>
                    <x-ui.input name="name" required autofocus />
                </x-ui.field>

                <x-ui.field label="Email address" name="email" required hint="This is the username they sign in with.">
                    <x-ui.input name="email" type="email" required />
                </x-ui.field>

                <x-ui.field label="Password" name="password" required hint="At least 12 characters.">
                    <x-ui.input name="password" type="password" required autocomplete="new-password" />
                </x-ui.field>

                <x-ui.field label="Confirm password" name="password_confirmation" required>
                    <x-ui.input name="password_confirmation" type="password" required autocomplete="new-password" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Role" description="What this account is allowed to do.">
            <x-ui.field label="Assigned role" name="role_id" required>
                <select
                    name="role_id"
                    id="role_id"
                    x-model="role"
                    required
                    class="block w-full rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand"
                >
                    <option value="">Select a role</option>
                    @foreach ($roles as $role)
                        <option
                            value="{{ $role->id }}"
                            data-slug="{{ $role->slug }}"
                            @selected(old('role_id') == $role->id)
                        >{{ $role->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            @php
                $guardianRoleId = $roles->firstWhere('slug', 'parent-guardian')?->id;
                $studentRoleId = $roles->firstWhere('slug', 'student')?->id;
                $teacherRoleId = $roles->firstWhere('slug', 'teacher')?->id;
            @endphp

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div x-show="role === '{{ $guardianRoleId }}'" x-cloak>
                    <x-ui.field label="Linked parent or guardian" name="guardian_id" required
                                hint="Only guardians without an account are listed.">
                        <x-ui.select
                            name="guardian_id"
                            placeholder="Select a guardian"
                            :options="$guardians->mapWithKeys(fn ($g) => [$g->id => $g->full_name])->all()"
                        />
                    </x-ui.field>
                </div>

                <div x-show="role === '{{ $studentRoleId }}'" x-cloak>
                    <x-ui.field label="Linked student" name="student_id" required
                                hint="Only students without an account are listed.">
                        <x-ui.select
                            name="student_id"
                            placeholder="Select a student"
                            :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' — '.$s->student_number])->all()"
                        />
                    </x-ui.field>
                </div>

                {{--
                    Required for a teacher, not optional. A teacher account that
                    points at no staff record cannot record a single mark:
                    AssessmentPolicy resolves the teacher through the user id,
                    finds nothing, and refuses. The account would look correct
                    in the user list and be useless in the classroom.
                --}}
                <div x-show="role === '{{ $teacherRoleId }}'" x-cloak>
                    <x-ui.field label="Linked staff record" name="teacher_id" required
                                hint="Only staff without an account are listed. Without this the teacher cannot record marks.">
                        <x-ui.select
                            name="teacher_id"
                            placeholder="Select a member of staff"
                            :options="$teachers->mapWithKeys(fn ($t) => [$t->id => $t->full_name.' — '.$t->staff_number])->all()"
                        />
                    </x-ui.field>
                </div>
            </div>

            @if ($teachers->isEmpty() && $teacherRoleId)
                <p x-show="role === '{{ $teacherRoleId }}'" x-cloak class="mt-3 text-sm text-amber-700">
                    Every member of staff already has an account.
                    <a href="{{ route('teachers.create') }}" class="font-medium underline underline-offset-2">Add a teacher</a>
                    first if this is someone new.
                </p>
            @endif
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('users.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Create account</x-ui.button>
        </div>
    </form>
</x-layouts.app>
