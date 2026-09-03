<x-layouts.app title="Website" heading="Website">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Website' => null]" />

    <x-ui.page-header
        title="Website content"
        description="Everything the public site shows. Changes appear immediately."
    >
        <x-slot:actions>
            <x-ui.button :href="route('home')" variant="secondary" target="_blank" rel="noopener">
                View the website
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div x-data="{ tab: 'pages' }">
        <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200">
            @foreach (['pages' => 'Pages', 'news' => 'News', 'gallery' => 'Gallery', 'social' => 'Social links', 'visibility' => 'What is published'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        class="relative px-4 py-2.5 text-sm font-medium transition-colors"
                        :class="tab === '{{ $key }}' ? 'text-brand' : 'text-slate-600 hover:text-brand'">
                    {{ $label }}
                    <span aria-hidden="true"
                          class="absolute inset-x-2 -bottom-px h-0.5 origin-center rounded-full bg-brand transition-transform duration-300"
                          :class="tab === '{{ $key }}' ? 'scale-x-100' : 'scale-x-0'"></span>
                </button>
            @endforeach
        </div>

        {{-- Pages --}}
        <div x-show="tab === 'pages'" x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <x-ui.card :padded="false">
                @if ($pages->isEmpty())
                    <x-ui.empty-state icon="▤" title="No pages yet"
                                      description="Run the website seeder to create the starting pages." />
                @else
                    <x-ui.table :headings="['Page', 'Summary', 'Status', '']">
                        @foreach ($pages as $page)
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-slate-900">{{ $page->title }}</span>
                                    <span class="block font-mono text-xs text-slate-500">/{{ $page->slug }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-600">{{ Str::limit($page->summary, 80) ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-ui.status-badge :status="$page->is_published ? 'active' : 'draft'" />
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-ui.button :href="route('website.pages.edit', $page)" variant="ghost" size="sm">Edit</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>

        {{-- News --}}
        <div x-show="tab === 'news'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <div class="grid gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" title="News posts" :padded="false">
                    @if ($news->isEmpty())
                        <x-ui.empty-state icon="✦" title="No news yet" description="Write your first post beside this list." />
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($news as $post)
                                <div class="flex items-start justify-between gap-3 px-5 py-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900">{{ $post->title }}</p>
                                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $post->excerpt }}</p>
                                        <p class="mt-1 text-[11px] text-slate-400">
                                            {{ $post->author?->name ?? 'School' }} ·
                                            {{ $post->published_at?->format('j M Y') }}
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-ui.status-badge :status="$post->is_published ? 'active' : 'draft'" />
                                        <x-ui.confirm
                                            :action="route('website.news.destroy', $post)"
                                            method="DELETE"
                                            title="Delete this news post?"
                                            :message="'&quot;'.$post->title.'&quot; will be removed from the website.'"
                                            confirm="Delete post"
                                            class="text-rose-600 hover:bg-rose-50"
                                        >Delete</x-ui.confirm>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="border-t border-slate-100 px-5 py-3">{{ $news->links() }}</div>
                    @endif
                </x-ui.card>

                <x-ui.card title="Write a post">
                    <form method="POST" action="{{ route('website.news.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <x-ui.field label="Title" name="title" required>
                            <x-ui.input name="title" required />
                        </x-ui.field>

                        <x-ui.field label="Summary" name="excerpt" hint="Shown on the news list.">
                            <x-ui.textarea name="excerpt" rows="2" />
                        </x-ui.field>

                        <x-ui.field label="Body" name="body" required>
                            <x-ui.textarea name="body" rows="8" required />
                        </x-ui.field>

                        <x-ui.field label="Image" name="image">
                            <input type="file" name="image" id="image" accept="image/*"
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                        </x-ui.field>

                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_published" value="1" checked
                                   class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                            <span class="text-sm text-slate-600">Publish immediately</span>
                        </label>

                        <x-ui.button type="submit" class="w-full">Save post</x-ui.button>
                    </form>
                </x-ui.card>
            </div>
        </div>

        {{-- Gallery --}}
        <div x-show="tab === 'gallery'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <div class="grid gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" title="Gallery">
                    @if ($gallery->isEmpty())
                        <x-ui.empty-state icon="▣" title="No images yet" description="Upload photographs of school life." />
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($gallery as $item)
                                <figure class="group relative overflow-hidden rounded-lg border border-slate-200">
                                    <img src="{{ Storage::disk('public')->url($item->image_path) }}"
                                         alt="{{ $item->caption ?? $item->title ?? 'Gallery image' }}"
                                         loading="lazy"
                                         class="aspect-square w-full object-cover transition-transform duration-500 group-hover:scale-105">

                                    <form method="POST" action="{{ route('website.gallery.destroy', $item) }}"
                                          class="absolute right-1.5 top-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="grid size-7 place-items-center rounded-lg bg-white/90 text-slate-600 shadow hover:bg-rose-50 hover:text-rose-600"
                                                aria-label="Remove image">&times;</button>
                                    </form>

                                    @if ($item->caption)
                                        <figcaption class="truncate px-2 py-1.5 text-[11px] text-slate-500">
                                            {{ $item->caption }}
                                        </figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>

                <x-ui.card title="Add images">
                    <form method="POST" action="{{ route('website.gallery.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <x-ui.field label="Album" name="album" hint="Groups images on the public page.">
                            <x-ui.input name="album" placeholder="Sports day" />
                        </x-ui.field>

                        <x-ui.field label="Caption" name="caption">
                            <x-ui.input name="caption" />
                        </x-ui.field>

                        <x-ui.field label="Images" name="images" required hint="Up to 12 at a time.">
                            <input type="file" name="images[]" id="images" accept="image/*" multiple required
                                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                        </x-ui.field>

                        <x-ui.button type="submit" class="w-full">Upload</x-ui.button>
                    </form>
                </x-ui.card>
            </div>
        </div>

        {{-- Social links --}}
        <div x-show="tab === 'social'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <x-ui.card class="max-w-2xl" title="Social media"
                       description="Leave a field blank to remove that link from the website.">
                <form method="POST" action="{{ route('website.social.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    @foreach ($platforms as $index => $platform)
                        @php $existing = $socialLinks->firstWhere('platform', $platform); @endphp

                        <div class="grid grid-cols-[8rem_1fr] items-center gap-3">
                            <label for="social-{{ $index }}" class="text-sm font-medium text-slate-700">{{ $platform }}</label>
                            <input type="hidden" name="links[{{ $index }}][platform]" value="{{ $platform }}">
                            <input type="url" name="links[{{ $index }}][url]" id="social-{{ $index }}"
                                   value="{{ $existing?->url }}" placeholder="https://"
                                   class="rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">
                        </div>
                    @endforeach

                    <div class="flex justify-end pt-2">
                        <x-ui.button type="submit">Save links</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
        {{-- What is published --}}
        <div x-show="tab === 'visibility'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <x-ui.card class="max-w-3xl" title="What the public website shows"
                       description="Anything switched off disappears from the site and from its navigation, leaving no dead links.">
                <form method="POST" action="{{ route('website.visibility') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-1">
                        @foreach ($visibilitySwitches as $key => $definition)
                            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg px-3 py-2.5 transition-colors hover:bg-slate-50">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-800">{{ $definition['label'] }}</span>
                                    <span class="block text-xs text-slate-500">{{ $definition['help'] }}</span>
                                </span>

                                <span class="relative inline-flex shrink-0">
                                    <input type="checkbox" name="visibility[{{ $key }}]" value="1"
                                           class="peer sr-only" @checked($visibility[$key] ?? false)>
                                    <span class="block h-6 w-11 rounded-full bg-slate-200 transition-colors duration-200 peer-checked:bg-brand"></span>
                                    <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                        <x-ui.button type="submit">Save what is published</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>