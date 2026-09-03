<x-layouts.app :title="'Edit '.$page->title" heading="Edit page">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Website' => route('website.index'), $page->title => null]" />

    <x-ui.page-header :title="$page->title" :description="'Public address: /'.$page->slug" />

    <form method="POST" action="{{ route('website.pages.update', $page) }}"
          enctype="multipart/form-data" class="max-w-3xl space-y-6"
          x-data="{ sections: {{ Js::from($page->sections ?: [['heading' => '', 'body' => '']]) }} }">
        @csrf
        @method('PUT')

        <x-ui.card title="Page details">
            <div class="space-y-5">
                <x-ui.field label="Title" name="title" required>
                    <x-ui.input name="title" :value="$page->title" required />
                </x-ui.field>

                <x-ui.field label="Introduction" name="summary" hint="The short paragraph under the page heading.">
                    <x-ui.textarea name="summary" :value="$page->summary" rows="3" />
                </x-ui.field>

                <x-ui.field label="Search description" name="meta_description"
                            hint="Shown by search engines. Around 150 characters works best.">
                    <x-ui.textarea name="meta_description" :value="$page->meta_description" rows="2" />
                </x-ui.field>

                <x-ui.field label="Banner image" name="hero_image">
                    <div class="flex items-center gap-3">
                        @if ($page->hero_image_path)
                            <img src="{{ Storage::disk('public')->url($page->hero_image_path) }}" alt=""
                                 class="h-16 w-28 rounded-lg border border-slate-200 object-cover">
                        @endif
                        <input type="file" name="hero_image" id="hero_image" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </div>
                </x-ui.field>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_published" value="1" @checked($page->is_published)
                           class="size-4 rounded border-slate-300 text-brand focus:ring-brand">
                    <span class="text-sm text-slate-600">Visible on the website</span>
                </label>
            </div>
        </x-ui.card>

        <x-ui.card title="Content blocks"
                   description="Each block becomes a section on the page. Empty blocks are ignored.">
            <div class="space-y-4">
                <template x-for="(section, index) in sections" :key="index">
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                  x-text="'Block ' + (index + 1)"></span>

                            <button type="button" @click="sections.splice(index, 1)"
                                    class="grid size-7 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                    aria-label="Remove block">&times;</button>
                        </div>

                        <input type="text" :name="`sections[${index}][heading]`" x-model="section.heading"
                               maxlength="180" placeholder="Heading"
                               class="mb-2 block w-full rounded-lg border-0 px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand">

                        <textarea :name="`sections[${index}][body]`" x-model="section.body" rows="4"
                                  maxlength="5000" placeholder="Write the content of this section..."
                                  class="block w-full rounded-lg border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand"></textarea>
                    </div>
                </template>
            </div>

            <x-ui.button type="button" variant="secondary" size="sm" class="mt-4"
                         @click="sections.push({ heading: '', body: '' })">
                Add a block
            </x-ui.button>
        </x-ui.card>

        <div class="flex items-center justify-end gap-2">
            <x-ui.button :href="route('website.index')" variant="secondary">Cancel</x-ui.button>
            <x-ui.button type="submit">Save page</x-ui.button>
        </div>
    </form>
</x-layouts.app>
