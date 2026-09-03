<x-layouts.app :title="'Edit · '.$announcement->title" heading="Edit announcement">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Announcements' => route('announcements.index'),
        'Edit' => null,
    ]" />

    <x-ui.page-header
        :title="'Edit '.$announcement->title"
        description="Correcting an announcement does not notify anyone again."
    />

    {{--
        Stated up front. Families were told when this was published, and sending
        the same message again because a typo was fixed trains people to ignore
        the notifications that matter. Something genuinely new is a new
        announcement, not an edit of an old one.
    --}}
    <div class="enter-rise mb-6 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4">
        <p class="text-sm text-slate-700">
            Published {{ $announcement->published_at?->format('j M Y, g:ia') }}.
            Saving changes here updates what people see, but sends no new notification.
        </p>
    </div>

    <form method="POST" action="{{ route('announcements.update', $announcement) }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Message">
            <div class="space-y-5">
                <x-ui.field label="Title" name="title" required>
                    <x-ui.input name="title" :value="$announcement->title" required autofocus />
                </x-ui.field>

                <x-ui.field label="Message" name="body" required>
                    <x-ui.textarea name="body" rows="6" :value="$announcement->body" required />
                </x-ui.field>

                <x-ui.field label="Category" name="category" required>
                    <x-ui.select name="category" required :selected="$announcement->category"
                                 :options="collect($categories)->mapWithKeys(fn ($c) => [$c => Str::headline($c)])->all()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Audience" description="Who should see this announcement.">
            @php $held = old('audience', $announcement->audience ?? []); @endphp

            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($audiences as $audience)
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 hover:bg-slate-50">
                        <input type="checkbox" name="audience[]" value="{{ $audience }}"
                               class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                               @checked(in_array($audience, (array) $held))>
                        <span class="text-sm text-slate-700">{{ Str::headline($audience) }}</span>
                    </label>
                @endforeach
            </div>

            @error('audience')
                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror

            <div class="mt-5">
                <x-ui.field label="Limit to one class" name="section_id" hint="Leave blank to reach the whole school.">
                    <x-ui.select name="section_id" placeholder="Whole school" :selected="$announcement->section_id"
                                 :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Scheduling">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Expires at" name="expires_at" hint="Leave blank to keep it indefinitely.">
                    <x-ui.input name="expires_at" type="datetime-local"
                                :value="$announcement->expires_at?->format('Y-m-d\TH:i')" />
                </x-ui.field>

                <x-ui.field label="Replace attachment" name="attachment"
                            :hint="$announcement->attachment_path ? 'Uploading a new file replaces the current one.' : 'PDF or image, up to 5 MB.'">
                    <input type="file" name="attachment" id="attachment" accept=".pdf,image/*"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                </x-ui.field>
            </div>

            <label class="mt-5 flex items-start gap-2.5">
                <input type="checkbox" name="is_emergency" value="1" id="is_emergency"
                       class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand"
                       @checked(old('is_emergency', $announcement->is_emergency))>
                <span class="text-sm text-slate-700">
                    Mark as urgent
                    <span class="block text-xs text-slate-500">Shown more prominently in the portals.</span>
                </span>
            </label>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('announcements.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save changes</x-ui.button>
        </div>
    </form>
</x-layouts.app>
