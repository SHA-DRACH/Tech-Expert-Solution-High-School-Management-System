<x-layouts.app title="New announcement" heading="New announcement">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Announcements' => route('announcements.index'), 'New' => null]" />

    <x-ui.page-header title="Write an announcement" description="Choose who should see it and when it expires." />

    <form method="POST" action="{{ route('announcements.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf

        <x-ui.card title="Message">
            <div class="space-y-5">
                <x-ui.field label="Title" name="title" required>
                    <x-ui.input name="title" required autofocus />
                </x-ui.field>

                <x-ui.field label="Message" name="body" required>
                    <x-ui.textarea name="body" rows="6" required />
                </x-ui.field>

                <x-ui.field label="Category" name="category" required>
                    <x-ui.select name="category" required
                                 :options="collect($categories)->mapWithKeys(fn ($c) => [$c => Str::headline($c)])->all()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Audience" description="Who should see this announcement.">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($audiences as $audience)
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 hover:bg-slate-50">
                        <input type="checkbox" name="audience[]" value="{{ $audience }}"
                               class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                               @checked(in_array($audience, old('audience', ['parents'])))>
                        <span class="text-sm text-slate-700">{{ Str::headline($audience) }}</span>
                    </label>
                @endforeach
            </div>

            @error('audience')
                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror

            <div class="mt-5">
                <x-ui.field label="Limit to one class" name="section_id" hint="Leave blank to reach the whole school.">
                    <x-ui.select name="section_id" placeholder="Whole school"
                                 :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Scheduling">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Publish at" name="published_at" hint="Leave blank to publish now.">
                    <x-ui.input name="published_at" type="datetime-local" />
                </x-ui.field>

                <x-ui.field label="Expires at" name="expires_at" hint="Leave blank to keep it indefinitely.">
                    <x-ui.input name="expires_at" type="datetime-local" />
                </x-ui.field>

                <x-ui.field label="Attachment" name="attachment" class="sm:col-span-2" hint="PDF or image, up to 5 MB.">
                    <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>
            </div>

            <label class="mt-5 flex items-start gap-2.5">
                <input type="checkbox" name="is_emergency" value="1"
                       class="mt-0.5 size-4 rounded border-slate-300 text-rose-500 focus:ring-rose-400">
                <span class="text-sm text-slate-700">
                    Mark as an emergency notice
                    <span class="block text-xs text-slate-500">Highlighted at the top of every portal it reaches.</span>
                </span>
            </label>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('announcements.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Publish announcement</x-ui.button>
        </div>
    </form>
</x-layouts.app>
