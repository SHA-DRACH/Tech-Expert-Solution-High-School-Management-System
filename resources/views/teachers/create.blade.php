<x-layouts.app title="Add teacher" heading="Add teacher">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Teachers & staff' => route('teachers.index'), 'Add teacher' => null]" />

    <x-ui.page-header title="Add a teacher" description="The staff number is generated automatically." />

    <form method="POST" action="{{ route('teachers.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf

        <x-ui.card title="Personal information">
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
                    <x-ui.select name="gender" placeholder="Not specified"
                                 :options="['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other']" />
                </x-ui.field>

                <x-ui.field label="Date of birth" name="date_of_birth">
                    <x-ui.input name="date_of_birth" type="date" />
                </x-ui.field>

                <x-ui.field label="Phone number" name="phone">
                    <x-ui.input name="phone" type="tel" placeholder="+231 77 000 0000" />
                </x-ui.field>

                <x-ui.field label="Email address" name="email">
                    <x-ui.input name="email" type="email" />
                </x-ui.field>

                <x-ui.field label="Photograph" name="photo" hint="JPG, PNG or WebP, up to 2 MB.">
                    <input type="file" name="photo" id="photo" accept="image/*"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>

                <x-ui.field label="Home address" name="address" class="sm:col-span-2">
                    <x-ui.textarea name="address" rows="2" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Employment">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Department" name="department_id">
                    <x-ui.select name="department_id" placeholder="Not assigned"
                                 :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
                </x-ui.field>

                <x-ui.field label="Employment type" name="employment_type">
                    <x-ui.select name="employment_type" placeholder="Select"
                                 :options="['Full time' => 'Full time', 'Part time' => 'Part time', 'Contract' => 'Contract', 'Volunteer' => 'Volunteer']" />
                </x-ui.field>

                <x-ui.field label="Date hired" name="hired_on">
                    <x-ui.input name="hired_on" type="date" />
                </x-ui.field>

                <x-ui.field label="Years of experience" name="experience_years">
                    <x-ui.input name="experience_years" type="number" min="0" max="70" />
                </x-ui.field>

                <x-ui.field label="Short biography" name="biography" class="sm:col-span-2"
                            hint="Shown publicly only if you publish this profile.">
                    <x-ui.textarea name="biography" rows="3" />
                </x-ui.field>
            </div>

            <label class="mt-5 flex items-start gap-2.5">
                <input type="checkbox" name="is_public" value="1"
                       class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand">
                <span class="text-sm text-slate-700">
                    Publish this profile
                    <span class="block text-xs text-slate-500">
                        Shows the name, photograph, department and approved qualifications on the website and parent portal.
                    </span>
                </span>
            </label>
        </x-ui.card>

        @if ($roles->isNotEmpty())
            <x-forms.portal-access
                :roles="$roles"
                :default-role="$defaultRole"
                who="this member of staff"
            />
        @endif

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('teachers.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Add teacher</x-ui.button>
        </div>
    </form>
</x-layouts.app>
