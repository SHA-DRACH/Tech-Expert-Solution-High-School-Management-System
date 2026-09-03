<x-layouts.app title="Gallery" heading="Gallery">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Gallery' => null]" />

    <x-ui.page-header
        title="Gallery"
        description="Photographs for the public website. Every image is named and described, so it can be found later and read aloud to someone who cannot see it."
    />

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            @forelse ($albums as $album => $images)
                <x-ui.card :title="$album" :description="$images->count().' '.Str::plural('image', $images->count())" :padded="false">
                    <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($images as $item)
                            <figure x-data="{ editing: false }" class="group overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                                    <img
                                        src="{{ Storage::disk('public')->url($item->image_path) }}"
                                        {{-- The title is the alt text. An empty alt on a
                                             photograph tells a screen reader nothing at all. --}}
                                        alt="{{ $item->title }}"
                                        loading="lazy"
                                        class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    >

                                    @unless ($item->is_published)
                                        <span class="absolute left-2 top-2">
                                            <x-ui.badge tone="warning">Hidden</x-ui.badge>
                                        </span>
                                    @endunless
                                </div>

                                <figcaption class="p-3.5">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $item->title }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $item->caption }}</p>

                                    <div class="mt-3 flex items-center gap-1">
                                        <x-ui.button size="sm" variant="ghost" x-on:click="editing = ! editing">Edit</x-ui.button>

                                        <x-ui.confirm
                                            :action="route('gallery.destroy', $item)"
                                            method="DELETE"
                                            title="Remove this image?"
                                            :message="'&quot;'.$item->title.'&quot; will be deleted from the gallery and from the website. This cannot be undone.'"
                                            confirm="Remove image"
                                            class="text-rose-600 hover:bg-rose-50"
                                        >Remove</x-ui.confirm>
                                    </div>

                                    <div x-show="editing" x-cloak x-collapse>
                                        <form method="POST" action="{{ route('gallery.update', $item) }}"
                                              class="mt-3 space-y-3 border-t border-slate-100 pt-3">
                                            @csrf
                                            @method('PUT')

                                            <x-ui.field label="Title" name="title" :id="'g-title-'.$item->id">
                                                <x-ui.input name="title" :id="'g-title-'.$item->id" :value="$item->title" :remember="false" />
                                            </x-ui.field>

                                            <x-ui.field label="Description" name="caption" :id="'g-cap-'.$item->id">
                                                <x-ui.textarea name="caption" rows="2" :value="$item->caption" />
                                            </x-ui.field>

                                            <div class="grid grid-cols-2 gap-3">
                                                <x-ui.field label="Album" name="album" :id="'g-album-'.$item->id">
                                                    <x-ui.input name="album" :id="'g-album-'.$item->id" :value="$item->album" :remember="false" />
                                                </x-ui.field>

                                                <x-ui.field label="Order" name="position" :id="'g-pos-'.$item->id">
                                                    <x-ui.input name="position" type="number" min="0" :id="'g-pos-'.$item->id"
                                                                :value="$item->position" :remember="false" />
                                                </x-ui.field>
                                            </div>

                                            <label class="flex items-center gap-2.5">
                                                <input type="checkbox" name="is_published" value="1"
                                                       class="size-4 rounded border-slate-300 text-brand focus:ring-brand"
                                                       @checked($item->is_published)>
                                                <span class="text-xs text-slate-700">Show on the public website</span>
                                            </label>

                                            <x-ui.button type="submit" size="sm">Save image</x-ui.button>
                                        </form>
                                    </div>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card>
                    <x-ui.empty-state
                        icon="▨"
                        title="No images yet"
                        description="Upload photographs of the school, its classes and its events. They appear in the gallery on your public website."
                    />
                </x-ui.card>
            @endforelse
        </div>

        <div class="lg:sticky lg:top-6 lg:self-start">
            <x-ui.card title="Upload images"
                       description="Both a title and a description are required — an unnamed photograph is one nobody can find or describe later.">
                <form method="POST" action="{{ route('gallery.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <x-ui.field label="Images" name="images" required
                                hint="JPG, PNG or WebP, up to 4 MB each. Up to 12 at once.">
                        <input type="file" name="images[]" id="images" accept="image/*" multiple
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </x-ui.field>

                    @error('images.*')
                        <p class="text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror

                    <x-ui.field label="Title" name="title" required
                                hint="What the image shows. Used as its alt text on the website.">
                        <x-ui.input name="title" placeholder="Grade 9 science practical" required />
                    </x-ui.field>

                    <x-ui.field label="Description" name="caption" required
                                hint="What it is for — where it will be used, or what it is meant to show.">
                        <x-ui.textarea name="caption" rows="3"
                                       placeholder="For the academics page, showing the laboratory in use during a chemistry lesson." />
                    </x-ui.field>

                    <x-ui.field label="Album" name="album"
                                hint="Groups related images together. Defaults to General.">
                        <x-ui.input name="album" list="existing-albums" placeholder="Sports day" />

                        <datalist id="existing-albums">
                            @foreach ($existingAlbums as $album)
                                <option value="{{ $album }}"></option>
                            @endforeach
                        </datalist>
                    </x-ui.field>

                    <label class="flex items-start gap-2.5">
                        <input type="checkbox" name="is_published" value="1" checked
                               class="mt-0.5 size-4 rounded border-slate-300 text-brand focus:ring-brand">
                        <span class="text-sm text-slate-700">
                            Show on the public website
                            <span class="block text-xs text-slate-500">
                                Untick to upload now and publish later.
                            </span>
                        </span>
                    </label>

                    <x-ui.button type="submit" class="w-full">Upload</x-ui.button>

                    <p class="text-xs text-slate-500">
                        Uploading several images at once gives them the same description and numbers the titles,
                        so they stay tellable apart.
                    </p>
                </form>
            </x-ui.card>

            <x-ui.card class="mt-5" title="Where these appear">
                <p class="text-sm text-slate-600">
                    Published images show in the gallery on your public website. Whether that page is public at all
                    is set under
                    <a href="{{ route('settings.index') }}" class="font-medium text-brand hover:underline">Settings</a>.
                </p>

                <p class="mt-3 text-sm text-slate-600">
                    {{ $items->where('is_published', true)->count() }} of {{ $items->count() }}
                    {{ Str::plural('image', $items->count()) }} currently published.
                </p>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
