<x-layouts.app title="Add student" heading="Add student">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Students' => route('students.index'), 'Add student' => null]" />

    <x-ui.page-header title="Add a student" description="Create a student record and link a parent or guardian." />

    <form method="POST" action="{{ route('students.store') }}" class="max-w-3xl space-y-6">
        @csrf

        <x-ui.card title="Student details" description="The student number is generated automatically.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="First name" name="first_name" required>
                    <x-ui.input name="first_name" required autofocus />
                </x-ui.field>

                <x-ui.field label="Middle name" name="middle_name">
                    <x-ui.input name="middle_name" />
                </x-ui.field>

                <x-ui.field label="Last name" name="last_name" required>
                    <x-ui.input name="last_name" required />
                </x-ui.field>

                <x-ui.field label="Gender" name="gender">
                    <x-ui.select name="gender" placeholder="Not specified" :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']" />
                </x-ui.field>

                <x-ui.field label="Date of birth" name="date_of_birth">
                    <x-ui.input name="date_of_birth" type="date" />
                </x-ui.field>

                <x-ui.field label="Nationality" name="nationality">
                    <x-ui.input name="nationality" placeholder="Liberian" />
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- Placing the student here rather than as a separate step afterwards.
             A student with no class appears on no register, no mark sheet and
             no report card, and that step was very easy to skip. --}}
        <x-ui.card title="Class placement"
                   :description="$currentYear ? 'Which class this student joins for '.$currentYear->name.'.' : 'Set up an academic year before enrolling students.'">
            @if ($currentYear === null || $sections->isEmpty())
                <p class="text-sm text-slate-500">
                    Set up an academic year and at least one class in
                    <a href="{{ route('academics.index') }}" class="font-medium text-brand hover:underline">Academic structure</a>
                    first. You can still add the student now and place them later.
                </p>
            @else
                <x-ui.field label="Class" name="section_id"
                            hint="Leave blank to add the student without placing them. They will not appear on any register until you do.">
                    <x-ui.select name="section_id" placeholder="Not placed yet"
                                 :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                </x-ui.field>

                <p class="mt-3 text-xs text-slate-500">
                    They are entered for every subject the class offers. Electives are deselected afterwards on
                    the student's own record.
                </p>
            @endif
        </x-ui.card>

        <x-ui.card title="Parent or guardian" description="Optional now; more guardians can be linked later.">
            @if ($guardians->isEmpty())
                <p class="text-sm text-slate-500">
                    No parents or guardians have been added yet.
                    @can('create', App\Models\Guardian::class)
                        <a href="{{ route('guardians.create') }}" class="font-medium text-brand hover:underline">Add one first</a>.
                    @endcan
                </p>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Primary guardian" name="guardian_id">
                        <x-ui.select
                            name="guardian_id"
                            placeholder="Link later"
                            :options="$guardians->mapWithKeys(fn ($g) => [$g->id => $g->full_name.($g->phone ? ' — '.$g->phone : '')])->all()"
                        />
                    </x-ui.field>

                    <x-ui.field label="Relationship" name="relationship">
                        <x-ui.input name="relationship" placeholder="Mother, Father, Guardian…" />
                    </x-ui.field>
                </div>
            @endif
        </x-ui.card>

        @if ($roles->isNotEmpty())
            <x-forms.portal-access
                :roles="$roles"
                :default-role="$defaultRole"
                who="this student"
            />
        @endif

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('students.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Create student</x-ui.button>
        </div>
    </form>
</x-layouts.app>
