<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Section;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);

        return view('announcements.index', [
            'announcements' => Announcement::with('author:id,name', 'section.schoolClass')
                ->latest('published_at')
                ->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);

        return view('announcements.create', [
            'sections' => Section::with('schoolClass')->get()->sortBy('full_name'),
            'audiences' => Announcement::AUDIENCES,
            'categories' => Announcement::CATEGORIES,
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['required', Rule::in(Announcement::CATEGORIES)],
            'audience' => ['required', 'array', 'min:1'],
            'audience.*' => [Rule::in(Announcement::AUDIENCES)],
            'section_id' => ['nullable', 'integer'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'is_emergency' => ['boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $section = isset($data['section_id']) ? Section::findOrFail($data['section_id']) : null;

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'section_id' => $section?->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'],
            'audience' => $data['audience'],
            'published_at' => $data['published_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'is_emergency' => $request->boolean('is_emergency'),
            'status' => 'published',
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('announcements', 'local')
                : null,
        ]);

        $notifier->announcementPublished($announcement);

        return redirect()
            ->route('announcements.index')
            ->with('status', 'Announcement published.');
    }

    public function edit(Request $request, Announcement $announcement): View
    {
        $this->authorizeAnnouncement($request, $announcement);

        return view('announcements.edit', [
            'announcement' => $announcement,
            'categories' => Announcement::CATEGORIES,
            'audiences' => Announcement::AUDIENCES,
            'sections' => Section::with('schoolClass')->get()
                ->sortBy(fn (Section $s) => $s->full_name)->values(),
        ]);
    }

    public function update(Request $request, Announcement $announcement, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAnnouncement($request, $announcement);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:8000'],
            'category' => ['required', Rule::in(Announcement::CATEGORIES)],
            'audience' => ['required', 'array', 'min:1'],
            'audience.*' => [Rule::in(Announcement::AUDIENCES)],
            'section_id' => ['nullable', 'integer'],
            'expires_at' => ['nullable', 'date'],
            'is_emergency' => ['boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Re-resolved through the tenant-scoped query.
        $section = filled($data['section_id'] ?? null) ? Section::findOrFail($data['section_id']) : null;

        $original = $announcement->only(['title', 'body', 'category', 'audience', 'expires_at', 'is_emergency']);

        $attributes = [
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'],
            'audience' => $data['audience'],
            'section_id' => $section?->id,
            'expires_at' => $data['expires_at'] ?? null,
            'is_emergency' => $request->boolean('is_emergency'),
        ];

        if ($request->hasFile('attachment')) {
            $previous = $announcement->attachment_path;

            $attributes['attachment_path'] = $request->file('attachment')->store('announcements', 'local');

            if ($previous) {
                Storage::disk('local')->delete($previous);
            }
        }

        $announcement->update($attributes);

        /*
         | Editing does not re-notify. Families were told when this was
         | published, and sending the same announcement again because a typo was
         | fixed trains people to ignore the notifications that matter. A
         | genuinely new message is a new announcement.
         */
        $audit->log('updated', 'Communication', "Announcement \"{$announcement->title}\" was edited.", $announcement, $original, $attributes);

        return redirect()
            ->route('announcements.index')
            ->with('status', 'Announcement updated. Nobody was notified again — publish a new one for that.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeAnnouncement($request, $announcement);

        $announcement->update(['status' => 'archived']);

        return back()->with('status', 'Announcement archived.');
    }

    /** Restore an announcement archived by mistake. */
    public function restore(Request $request, Announcement $announcement, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAnnouncement($request, $announcement);

        $announcement->update(['status' => 'published']);

        $audit->log('restored', 'Communication', "Announcement \"{$announcement->title}\" was restored.", $announcement);

        return back()->with('status', 'Announcement restored.');
    }

    protected function authorizeAnnouncement(Request $request, Announcement $announcement): void
    {
        abort_unless($request->user()->hasPermission('announcements.manage'), 403);
        abort_unless($announcement->school_id === $request->user()->school_id, 403);
    }
}
