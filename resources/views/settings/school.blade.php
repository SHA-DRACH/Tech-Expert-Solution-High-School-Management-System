<x-layouts.app title="School settings" heading="School settings">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'School settings' => null]" />

    <x-ui.page-header
        title="School settings"
        description="Profile, contact details and branding for {{ $school->name }}."
    />

    <form method="POST" action="{{ route('settings.school.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="School profile">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="School name" name="name" required class="sm:col-span-2">
                    <x-ui.input name="name" :value="$school->name" required />
                </x-ui.field>

                <x-ui.field label="Short name" name="short_name" hint="Used where space is tight, such as the sidebar.">
                    <x-ui.input name="short_name" :value="$school->short_name" />
                </x-ui.field>

                <x-ui.field label="Motto" name="motto">
                    <x-ui.input name="motto" :value="$school->motto" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Contact information" description="Shown on the public website and on printed documents.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Email address" name="email">
                    <x-ui.input name="email" type="email" :value="$school->email" />
                </x-ui.field>

                <x-ui.field label="Phone number" name="phone">
                    <x-ui.input name="phone" type="tel" :value="$school->phone" />
                </x-ui.field>

                <x-ui.field label="Website" name="website">
                    <x-ui.input name="website" type="url" :value="$school->website" placeholder="https://" />
                </x-ui.field>

                <x-ui.field label="Address" name="address" class="sm:col-span-2">
                    <x-ui.textarea name="address" :value="$school->address" rows="3" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Branding" description="Your colours and logo are applied across the whole platform.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Primary colour" name="primary_color" required>
                    <div class="flex items-center gap-2">
                        <input
                            type="color"
                            name="primary_color"
                            id="primary_color"
                            value="{{ old('primary_color', $school->primary_color) }}"
                            class="h-10 w-16 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                        >
                        <span class="font-mono text-xs text-slate-500">{{ $school->primary_color }}</span>
                    </div>
                </x-ui.field>

                <x-ui.field label="Secondary colour" name="secondary_color" required>
                    <div class="flex items-center gap-2">
                        <input
                            type="color"
                            name="secondary_color"
                            id="secondary_color"
                            value="{{ old('secondary_color', $school->secondary_color) }}"
                            class="h-10 w-16 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                        >
                        <span class="font-mono text-xs text-slate-500">{{ $school->secondary_color }}</span>
                    </div>
                </x-ui.field>

                <x-ui.field label="Logo" name="logo" hint="PNG, JPG, SVG or WebP, up to 2 MB.">
                    <div class="flex items-center gap-3">
                        @if ($school->logo_path)
                            <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt="Current logo"
                                 class="size-12 rounded-lg border border-slate-200 object-cover">
                        @endif
                        <input type="file" name="logo" id="logo" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </div>
                </x-ui.field>

                <x-ui.field label="Favicon" name="favicon" hint="PNG, ICO or SVG, up to 512 KB.">
                    <div class="flex items-center gap-3">
                        @if ($school->favicon_path)
                            <img src="{{ Storage::disk('public')->url($school->favicon_path) }}" alt="Current favicon"
                                 class="size-8 rounded border border-slate-200 object-cover">
                        @endif
                        <input type="file" name="favicon" id="favicon" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </div>
                </x-ui.field>
            </div>
        </x-ui.card>

        @if ($backup)
            <x-ui.card title="Backups" description="Taken nightly by the scheduler.">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-slate-700">
                            @if (! $backup['configured'])
                                No backup has run yet.
                            @else
                                Last backup {{ $backup['last_backup_at']?->diffForHumans() }}
                                ({{ $backup['size'] }}), {{ $backup['count'] }} kept.
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Run by hand with <code class="font-mono">php artisan gsms:backup</code>.
                        </p>
                    </div>

                    @if ($backup['is_overdue'])
                        <x-ui.badge tone="danger">Overdue</x-ui.badge>
                    @else
                        <x-ui.badge tone="success">Healthy</x-ui.badge>
                    @endif
                </div>
            </x-ui.card>
        @endif

        <div class="flex items-center justify-end gap-2">
            <x-ui.button type="submit">Save settings</x-ui.button>
        </div>
    </form>
</x-layouts.app>
