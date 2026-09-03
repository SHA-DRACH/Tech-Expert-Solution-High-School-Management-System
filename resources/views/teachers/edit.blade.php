<x-layouts.app :title="'Edit '.$teacher->full_name" heading="Edit staff record">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Teachers & staff' => route('teachers.index'),
        $teacher->full_name => route('teachers.show', $teacher),
        'Edit' => null,
    ]" />

    <x-ui.page-header
        :title="'Edit '.$teacher->full_name"
        :description="'Staff number '.$teacher->staff_number.'. The staff number itself never changes.'"
    />

    <form method="POST" action="{{ route('teachers.update', $teacher) }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Personal information">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="First name" name="first_name" required>
                    <x-ui.input name="first_name" :value="$teacher->first_name" required autofocus />
                </x-ui.field>

                <x-ui.field label="Middle name" name="middle_name">
                    <x-ui.input name="middle_name" :value="$teacher->middle_name" />
                </x-ui.field>

                <x-ui.field label="Last name" name="last_name" required>
                    <x-ui.input name="last_name" :value="$teacher->last_name" required />
                </x-ui.field>

                <x-ui.field label="Gender" name="gender">
                    <x-ui.select name="gender" placeholder="Not specified" :selected="$teacher->gender"
                                 :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']" />
                </x-ui.field>

                <x-ui.field label="Date of birth" name="date_of_birth">
                    <x-ui.input name="date_of_birth" type="date" :value="$teacher->date_of_birth?->toDateString()" />
                </x-ui.field>

                <x-ui.field label="Phone number" name="phone">
                    <x-ui.input name="phone" type="tel" :value="$teacher->phone" placeholder="+231 77 000 0000" />
                </x-ui.field>

                <x-ui.field label="Email address" name="email">
                    <x-ui.input name="email" type="email" :value="$teacher->email" />
                </x-ui.field>

                <x-ui.field label="Photograph" name="photo"
                            hint="{{ $teacher->photo_path ? 'Uploading a new one replaces the current photograph.' : 'JPG, PNG or WebP, up to 2 MB.' }}">
                    <input type="file" name="photo" id="photo" accept="image/*"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>

                <x-ui.field label="Home address" name="address" class="sm:col-span-2">
                    <x-ui.textarea name="address" rows="2" :value="$teacher->address" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Employment">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Department" name="department_id">
                    <x-ui.select name="department_id" placeholder="Not assigned" :selected="$teacher->department_id"
                                 :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Employment type" name="employment_type">
                    <x-ui.select name="employment_type" placeholder="Select" :selected="$teacher->employment_type"
                                 :options="['Full time' => 'Full time', 'Part time' => 'Part time', 'Contract' => 'Contract', 'Volunteer' => 'Volunteer']" />
                </x-ui.field>

                <x-ui.field label="Date hired" name="hired_on">
                    <x-ui.input name="hired_on" type="date" :value="$teacher->hired_on?->toDateString()" />
                </x-ui.field>

                <x-ui.field label="Years of experience" name="experience_years">
                    <x-ui.input name="experience_years" type="number" min="0" max="70" :value="$teacher->experience_years" />
                </x-ui.field>

                <x-ui.field label="Employment status" name="status" required
                            hint="Kept separately from archiving, so a record can say why someone left.">
                    <x-ui.select name="status" :selected="$teacher->status"
                                 :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => Str::headline($s)])->all()" />
                </x-ui.field>

                <x-ui.field label="Short biography" name="biography" class="sm:col-span-2"
                            hint="Shown publicly only if you publish this profile.">
                    <x-ui.textarea name="biography" rows="3" :value="$teacher->biography" />
                </x-ui.field>
            </div>

            <label class="mt-5 flex items-start gap-2.5">
                <input type="checkbox" name="is_public" value="1" id="is_public"
                       class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand"
                       @checked(old('is_public', $teacher->is_public))>
                <span class="text-sm text-slate-700">
                    Publish this profile
                    <span class="block text-xs text-slate-500">
                        Shows the name, photograph, department and approved qualifications on the website and parent portal.
                    </span>
                </span>
            </label>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                @can('teachers.archive')
                    <x-ui.confirm
                        :action="route('teachers.destroy', $teacher)"
                        method="DELETE"
                        title="Archive this staff record?"
                        :message="$teacher->full_name.' will be removed from the staff list and the public website. Their record, and the marks and lessons attached to it, are kept and can be restored.'"
                        confirm="Archive record"
                    >Archive record</x-ui.confirm>
                @endcan
            </div>

            <div class="flex items-center gap-2">
                <x-ui.button :href="route('teachers.show', $teacher)" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
