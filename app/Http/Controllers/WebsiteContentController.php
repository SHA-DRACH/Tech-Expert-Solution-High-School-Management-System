<?php

namespace App\Http\Controllers;

use App\Models\GalleryItem;
use App\Models\NewsPost;
use App\Models\SocialLink;
use App\Models\WebsitePage;
use App\Services\AuditLogger;
use App\Services\PublicVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The website CMS (spec section 49).
 *
 * Everything the public site shows — page copy, news, gallery, social links —
 * is edited here, so a school changes its own website without a developer.
 */
class WebsiteContentController extends Controller
{
    public function index(Request $request, PublicVisibility $visibility): View
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        return view('website.index', [
            'visibilitySwitches' => PublicVisibility::SWITCHES,
            'visibility' => $visibility->all(),
            'pages' => WebsitePage::orderBy('position')->orderBy('title')->get(),
            // The listing prints each post's author, so the relation is loaded
            // with the page rather than one query per row.
            'news' => NewsPost::with('author:id,name')->latest('published_at')->paginate(10),
            'gallery' => GalleryItem::orderBy('album')->orderBy('position')->get(),
            'socialLinks' => SocialLink::orderBy('position')->get(),
            'pageKeys' => WebsitePage::KEYS,
            'platforms' => SocialLink::PLATFORMS,
        ]);
    }

    public function editPage(Request $request, WebsitePage $websitePage): View
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);
        abort_unless($websitePage->school_id === $request->user()->school_id, 403);

        return view('website.edit-page', ['page' => $websitePage]);
    }

    public function updatePage(Request $request, WebsitePage $websitePage, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);
        abort_unless($websitePage->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'is_published' => ['boolean'],
            'sections' => ['array', 'max:20'],
            'sections.*.heading' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:5000'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $attributes = [
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_published' => $request->boolean('is_published'),
            // Empty rows are dropped so the page never renders a blank band.
            'sections' => collect($data['sections'] ?? [])
                ->filter(fn (array $section) => filled($section['heading'] ?? null) || filled($section['body'] ?? null))
                ->values()
                ->all(),
        ];

        if ($request->hasFile('hero_image')) {
            $previous = $websitePage->hero_image_path;

            $attributes['hero_image_path'] = $request->file('hero_image')
                ->store("schools/{$websitePage->school_id}/website", 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $websitePage->update($attributes);

        $audit->log('updated', 'Website', "The \"{$websitePage->title}\" page was updated.", $websitePage);

        return redirect()->route('website.index')->with('status', 'Page updated.');
    }

    /**
     * What the school publishes (spec sections 10 and 11). Anything not ticked
     * is switched off, so removing something from the site is one action.
     */
    public function updateVisibility(Request $request, PublicVisibility $visibility, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        $before = $visibility->all();

        $visibility->update($request->user()->school_id, $request->input('visibility', []));

        $audit->log('updated', 'Website', 'What the public website shows was changed.', null,
            ['visibility' => $before], ['visibility' => $visibility->all()]);

        return back()->with('status', 'Website visibility updated.');
    }

    /* -------------------------------------------------------------- news */

    public function storeNews(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $post = NewsPost::create([
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug(NewsPost::class, $data['title']),
            'excerpt' => $data['excerpt'] ?? Str::limit(strip_tags($data['body']), 160),
            'body' => $data['body'],
            'published_at' => $data['published_at'] ?? now(),
            'is_published' => $request->boolean('is_published'),
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('schools/'.$request->user()->school_id.'/news', 'public')
                : null,
        ]);

        $audit->log('created', 'Website', "News post \"{$post->title}\" was created.", $post);

        return back()->with('status', 'News post saved.');
    }

    public function updateNews(Request $request, NewsPost $newsPost, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);
        abort_unless($newsPost->school_id === $request->user()->school_id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['boolean'],
        ]);

        $newsPost->update($data + ['is_published' => $request->boolean('is_published')]);

        $audit->log('updated', 'Website', "News post \"{$newsPost->title}\" was updated.", $newsPost);

        return back()->with('status', 'News post updated.');
    }

    public function destroyNews(Request $request, NewsPost $newsPost, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);
        abort_unless($newsPost->school_id === $request->user()->school_id, 403);

        $title = $newsPost->title;

        if ($newsPost->image_path) {
            Storage::disk('public')->delete($newsPost->image_path);
        }

        $newsPost->delete();

        $audit->log('deleted', 'Website', "News post \"{$title}\" was deleted.");

        return back()->with('status', 'News post deleted.');
    }

    /* ----------------------------------------------------------- gallery */

    public function storeGallery(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:300'],
            'album' => ['nullable', 'string', 'max:80'],
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $position = (int) GalleryItem::max('position');

        foreach ($request->file('images') as $image) {
            GalleryItem::create([
                'title' => $data['title'] ?? null,
                'caption' => $data['caption'] ?? null,
                'album' => $data['album'] ?? 'General',
                'image_path' => $image->store('schools/'.$request->user()->school_id.'/gallery', 'public'),
                'position' => ++$position,
                'is_published' => true,
            ]);
        }

        $count = count($request->file('images'));

        $audit->log('created', 'Website', "{$count} images were added to the gallery.");

        return back()->with('status', $count.' '.Str::plural('image', $count).' added to the gallery.');
    }

    public function destroyGallery(Request $request, GalleryItem $galleryItem, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);
        abort_unless($galleryItem->school_id === $request->user()->school_id, 403);

        Storage::disk('public')->delete($galleryItem->image_path);

        $galleryItem->delete();

        $audit->log('deleted', 'Website', 'A gallery image was removed.');

        return back()->with('status', 'Image removed.');
    }

    /* ----------------------------------------------------- social links */

    public function updateSocialLinks(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('website.manage'), 403);

        $data = $request->validate([
            'links' => ['array', 'max:10'],
            'links.*.platform' => ['required', Rule::in(SocialLink::PLATFORMS)],
            'links.*.url' => ['nullable', 'url', 'max:255'],
        ]);

        foreach ($data['links'] ?? [] as $index => $link) {
            if (blank($link['url'] ?? null)) {
                SocialLink::where('platform', $link['platform'])->delete();

                continue;
            }

            SocialLink::updateOrCreate(
                ['school_id' => $request->user()->school_id, 'platform' => $link['platform']],
                ['url' => $link['url'], 'position' => $index],
            );
        }

        $audit->log('updated', 'Website', 'Social media links were updated.');

        return back()->with('status', 'Social links updated.');
    }

    /** A slug that is unique within this school. */
    protected function uniqueSlug(string $model, string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
