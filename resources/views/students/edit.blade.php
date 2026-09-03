<x-layouts.app :title="'Edit '.$student->full_name" heading="Edit student">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Students' => route('students.index'),
        $student->full_name => route('students.show', $student),
        'Edit' => null,
    ]" />

    <x-ui.page-header
        :title="'Edit '.$student->full_name"
        :description="'Student number '.$student->student_number.'. The number itself never changes.'"
    />

    <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Personal information">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="First name" name="first_name" required>
                    <x-ui.input name="first_name" :value="$student->first_name" required autofocus />
                </x-ui.field>

                <x-ui.field label="Middle name" name="middle_name">
                    <x-ui.input name="middle_name" :value="$student->middle_name" />
                </x-ui.field>

                <x-ui.field label="Last name" name="last_name" required>
                    <x-ui.input name="last_name" :value="$student->last_name" required />
                </x-ui.field>

                <x-ui.field label="Gender" name="gender">
                    <x-ui.select name="gender" placeholder="Not specified" :selected="$student->gender"
                                 :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']" />
                </x-ui.field>

                <x-ui.field label="Date of birth" name="date_of_birth">
                    <x-ui.input name="date_of_birth" type="date" :value="$student->date_of_birth?->toDateString()" />
                </x-ui.field>

                <x-ui.field label="Nationality" name="nationality">
                    <x-ui.input name="nationality" :value="$student->nationality" placeholder="Liberian" />
                </x-ui.field>

                <x-ui.field label="Status" name="status" required
                            hint="Transferred, graduated and withdrawn keep the record and its history; archiving is separate.">
                    <x-ui.select name="status" :selected="$student->status"
                                 :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
                </x-ui.field>
            </div>

            {{-- The photograph goes on the report card, which is what makes a
                 printed card belong to a child rather than to a row in a table. --}}
            <div class="mt-5 flex flex-wrap items-center gap-5 border-t border-slate-100 pt-5">
                @if ($student->photo_path)
                    <img src="{{ Storage::disk('public')->url($student->photo_path) }}" alt=""
                         class="size-20 rounded-lg object-cover">
                @else
                    <span class="grid size-20 place-items-center rounded-lg bg-brand/10 font-display text-xl font-bold text-brand">
                        {{ Str::substr($student->first_name, 0, 1) }}{{ Str::substr($student->last_name, 0, 1) }}
                    </span>
                @endif

                <div class="min-w-56 flex-1">
                    <x-ui.field label="Photograph" name="photo"
                                hint="Appears on this student's report card. JPG, PNG or WebP, up to 2 MB.">
                        <input type="file" name="photo" id="photo" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Parents & guardians"
                   description="Adding a guardian here does not remove the ones already linked.">
            @if ($student->guardians->isNotEmpty())
                <ul class="mb-5 space-y-2 text-sm">
                    @foreach ($student->guardians as $existing)
                        <li class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <span>
                                <span class="font-medium text-slate-900">{{ $existing->full_name }}</span>
                                <span class="ml-1.5 text-xs text-slate-500">{{ $existing->pivot->relationship }}</span>
                            </span>
                            @if ($existing->pivot->is_primary)
                                <x-ui.badge tone="info">Primary</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Add a guardian" name="guardian_id">
                    <x-ui.select
                        name="guardian_id"
                        placeholder="Select a guardian"
                        :options="$guardians->mapWithKeys(fn ($g) => [$g->id => $g->full_name])->all()"
                    />
                </x-ui.field>

                <x-ui.field label="Relationship" name="relationship">
                    <x-ui.input name="relationship" placeholder="Mother, Father, Guardian…" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                @can('archive', $student)
                    <x-ui.confirm
                        :action="route('students.destroy', $student)"
                        method="DELETE"
                        title="Archive this student?"
                        :message="$student->full_name.' will be removed from the active roll. Their enrolments, marks, attendance and invoices are all kept, and the record can be restored.'"
                        confirm="Archive student"
                    >Archive student</x-ui.confirm>
                @endcan
            </div>

            <div class="flex items-center gap-2">
                <x-ui.button :href="route('students.show', $student)" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
