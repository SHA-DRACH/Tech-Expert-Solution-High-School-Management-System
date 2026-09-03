<?php

namespace App\Http\Controllers;

use App\Models\GalleryItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The photo gallery, on its own screen (spec section 51).
 *
 * It lived inside a tab of the website editor, which made it hard to find and
 * meant uploading a photograph was three clicks deep in a page about page
 * content. It gets its own place in the sidebar instead.
 *
 * Every image is asked for a **title** - what it is - and a **description** -
 * why it is here. Both are required. An untitled photograph is unusable to
 * everyone except the person who uploaded it: it cannot be searched for, and a
 * screen reader announces nothing at all, so the title becomes the image's alt
 * text on the public site rather than being left empty.
 */
class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        $items = GalleryItem::orderBy('album')->orderBy('position')->orderByDesc('id')->get();

        return view('gallery.index', [
            'albums' => $items->groupBy(fn (GalleryItem $item) => $item->album ?: 'General'),
            'items' => $items,
            // Offered as suggestions so a school does not end up with
            // "Sports day", "sports day" and "Sports Day" as three albums.
            'existingAlbums' => $items->pluck('album')->filter()->unique()->sort()->values(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'caption' => ['required', 'string', 'max:300'],
            'album' => ['nullable', 'string', 'max:80'],
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_published' => ['boolean'],
        ], [
            'title.required' => 'Give the image a title. It is what identifies it here, and what a screen reader reads out on the website.',
            'caption.required' => 'Say what the image is for. A photograph nobody can describe is one nobody can use.',
            'images.required' => 'Choose at least one image.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.max' => 'Each image must be 4 MB or smaller.',
        ], [
            'caption' => 'description',
            'images.*' => 'image',
        ]);

        /*
         | Numbered from a single read taken before the loop. Re-querying MAX()
         | per image would see this transaction's own uncommitted rows on some
         | drivers and not others, and hand two images the same position.
         */
        $position = (int) GalleryItem::max('position');

        $album = filled($data['album'] ?? null) ? trim($data['album']) : 'General';
        $files = $request->file('images');
        $multiple = count($files) > 1;

        foreach ($files as $index => $image) {
            GalleryItem::create([
                // Several images uploaded together are numbered, so they stay
                // tellable apart instead of sharing one title.
                'title' => $multiple ? $data['title'].' ('.($index + 1).')' : $data['title'],
                'caption' => $data['caption'],
                'album' => $album,
                'image_path' => $image->store('schools/'.$request->user()->school_id.'/gallery', 'public'),
                'position' => ++$position,
                'is_published' => $request->boolean('is_published', true),
            ]);
        }

        $count = count($files);

        $audit->log('created', 'Website', "{$count} gallery ".\Illuminate\Support\Str::plural('image', $count)." was added to {$album}.");

        return back()->with('status', $count.' '.\Illuminate\Support\Str::plural('image', $count).' added to '.$album.'.');
    }

    public function update(Request $request, GalleryItem $galleryItem, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeItem($request, $galleryItem);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'caption' => ['required', 'string', 'max:300'],
            'album' => ['nullable', 'string', 'max:80'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['boolean'],
        ], [], ['caption' => 'description']);

        $original = $galleryItem->only(['title', 'caption', 'album', 'is_published']);

        $galleryItem->update([
            'title' => $data['title'],
            'caption' => $data['caption'],
            'album' => filled($data['album'] ?? null) ? trim($data['album']) : 'General',
            'position' => $data['position'] ?? $galleryItem->position,
            'is_published' => $request->boolean('is_published'),
        ]);

        $audit->log('updated', 'Website', "Gallery image \"{$galleryItem->title}\" was updated.", $galleryItem, $original, $data);

        return back()->with('status', 'Image updated.');
    }

    public function destroy(Request $request, GalleryItem $galleryItem, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeItem($request, $galleryItem);

        $title = $galleryItem->title ?: 'An image';

        // The row goes first. If the delete fails after the file is gone, the
        // gallery would be left with an entry pointing at nothing.
        $path = $galleryItem->image_path;

        $galleryItem->delete();

        Storage::disk('public')->delete($path);

        $audit->log('deleted', 'Website', "Gallery image \"{$title}\" was removed.", null);

        return back()->with('status', "\"{$title}\" was removed.");
    }

    protected function authorizeItem(Request $request, GalleryItem $item): void
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        // The global scope already filters the binding; this fails closed if it
        // is ever removed (section 59).
        abort_unless($item->school_id === $request->user()->school_id, 403);
    }
}
