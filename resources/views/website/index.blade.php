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
            @foreach (['pages' => 'Pages', 'menu' => 'Menu & footer', 'news' => 'News', 'gallery' => 'Gallery', 'social' => 'Social links', 'visibility' => 'What is published'] as $key => $label)
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
                                    @if ($page->isCustom())
                                        <x-ui.badge class="ml-1">Your page</x-ui.badge>
                                    @endif
                                    <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener"
                                       class="block font-mono text-xs text-slate-500 hover:text-brand hover:underline">{{ parse_url($page->publicUrl(), PHP_URL_PATH) ?: '/' }}</a>
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-600">{{ Str::limit($page->summary, 80) ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-ui.status-badge :status="$page->is_published ? 'active' : 'draft'" />
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-ui.button :href="route('website.pages.edit', $page)" variant="ghost" size="sm">Edit</x-ui.button>
                                        @if ($page->isCustom())
                                            <x-ui.confirm
                                                :action="route('website.pages.destroy', $page)"
                                                method="DELETE"
                                                title="Delete this page?"
                                                :message="'“'.($page->title).'” will be removed from the website, and from the menu.'"
                                                confirm="Delete page"
                                                class="text-rose-600 hover:bg-rose-50"
                                            >Delete</x-ui.confirm>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
            <x-ui.card class="mt-6 max-w-2xl" title="Add a page"
                       description="A page of your own, such as School rules, Uniform or Transport. It starts hidden; write it, publish it, then add it to the menu.">
                <form method="POST" action="{{ route('website.pages.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-56 flex-1">
                        <x-ui.field label="Page title" name="title" required>
                            <x-ui.input name="title" placeholder="e.g. School rules" required :remember="false" />
                        </x-ui.field>
                    </div>
                    <x-ui.button type="submit">Create page</x-ui.button>
                </form>
            </x-ui.card>
        </div>

        {{-- Menu & footer --}}
        <div x-show="tab === 'menu'" x-cloak x-transition:enter="transition duration-300" x-transition:enter-start="opacity-0 translate-y-2">
            <form method="POST" action="{{ route('website.menu.update') }}" class="max-w-3xl space-y-6"
                  x-data="{ items: {{ Js::from(collect($menu)->map(fn ($i) => ['label' => $i['label'] ?? '', 'target' => $i['target'] ?? 'home', 'url' => $i['url'] ?? '', 'visible' => (bool) ($i['visible'] ?? true)])->values()) }} }">
                @csrf
                @method('PUT')

                <x-ui.card title="Menu" :padded="false"
                           description="The links across the top of the website, in order. Point a link at any page, or type an address. Sections switched off under “What is published” are hidden automatically.">
                    @error('menu')<p class="px-5 pt-4 text-sm text-rose-600">{{ $message }}</p>@enderror
                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'menu.'))
                            <p class="px-5 pt-2 text-sm text-rose-600">{{ $messages[0] }}</p>
                        @endif
                    @endforeach

                    <ul class="divide-y divide-slate-100">
                        <template x-for="(item, index) in items" :key="index">
                            <li class="grid gap-3 px-5 py-3 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-center">
                                <div class="flex items-center gap-1">
                                    <button type="button" class="grid size-7 place-items-center rounded text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                                            :disabled="index === 0" @click="items.splice(index - 1, 0, items.splice(index, 1)[0])" aria-label="Move up">▲</button>
                                    <button type="button" class="grid size-7 place-items-center rounded text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                                            :disabled="index === items.length - 1" @click="items.splice(index + 1, 0, items.splice(index, 1)[0])" aria-label="Move down">▼</button>
                                </div>

                                <input type="text" :name="`menu[${index}][label]`" x-model="item.label" maxlength="40" required
                                       placeholder="Label" aria-label="Link label"
                                       class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">

                                <div class="space-y-2">
                                    <select :name="`menu[${index}][target]`" x-model="item.target" aria-label="Link goes to"
                                            class="block w-full rounded-lg border-0 py-2 pl-3 pr-8 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                        @foreach ($menuTargets as $key => $title)
                                            <option value="{{ $key }}">{{ $title }}</option>
                                        @endforeach
                                        <option value="url">Another address…</option>
                                    </select>
                                    <input type="text" x-show="item.target === 'url'" x-cloak :name="`menu[${index}][url]`" x-model="item.url"
                                           placeholder="/pages/school-rules or https://…" aria-label="Link address"
                                           class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                </div>

                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input type="hidden" :name="`menu[${index}][visible]`" value="0">
                                        <input type="checkbox" :name="`menu[${index}][visible]`" value="1" x-model="item.visible"
                                               class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                                        Show
                                    </label>
                                    <button type="button" @click="items.splice(index, 1)" aria-label="Remove link"
                                            class="grid size-7 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600">&times;</button>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3">
                        <x-ui.button type="button" variant="secondary" size="sm"
                                     x-show="items.length < {{ App\Support\SiteMenu::MAX_ITEMS }}"
                                     @click="items.push({ label: '', target: 'url', url: '', visible: true })">
                            Add a link
                        </x-ui.button>
                        <span class="text-xs text-slate-500">Up to {{ App\Support\SiteMenu::MAX_ITEMS }} links.</span>
                    </div>
                </x-ui.card>

                <x-ui.card title="Footer">
                    <x-ui.field label="Footer text" name="footer_text"
                                hint="A sentence or two about the school, shown at the bottom of every page. Leave empty to show the motto.">
                        <x-ui.textarea name="footer_text" rows="3" :value="$footerText" />
                    </x-ui.field>
                    <p class="mt-3 text-xs text-slate-500">
                        School name, logo, address, phone and email come from
                        @can('settings.manage')<a href="{{ route('settings.school.edit') }}" class="underline">Settings › School profile</a>@else Settings › School profile @endcan.
                    </p>
                </x-ui.card>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <x-ui.button type="submit">Save menu &amp; footer</x-ui.button>
                </div>
            </form>

            <form method="POST" action="{{ route('website.menu.reset') }}" class="mt-3 max-w-3xl text-right"
                  onsubmit="return confirm('Put the menu back to the standard links? Your own links will be removed from the menu.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-slate-500 underline hover:text-slate-700">Reset menu to the standard links</button>
            </form>
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
                                            :message="'“'.$post->title.'” will be removed from the website.'"
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