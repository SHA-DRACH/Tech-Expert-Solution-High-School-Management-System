<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('events.manage'), 403);

        return view('events.index', [
            'upcoming' => Event::upcoming()->get(),
            'past' => Event::where('starts_at', '<', now()->startOfDay())->latest('starts_at')->limit(20)->get(),
            'categories' => Event::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('events.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category' => ['required', Rule::in(Event::CATEGORIES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:180'],
            'is_public' => ['boolean'],
        ]);

        Event::create($data + [
            'created_by' => $request->user()->id,
            'is_public' => $request->boolean('is_public'),
            'status' => 'scheduled',
        ]);

        return back()->with('status', 'Event added to the calendar.');
    }

    public function update(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category' => ['required', Rule::in(Event::CATEGORIES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:180'],
            'status' => ['required', Rule::in(['scheduled', 'cancelled', 'completed'])],
            'is_public' => ['boolean'],
        ]);

        $original = $event->only(array_keys($data));

        $event->update($data + ['is_public' => $request->boolean('is_public')]);

        $audit->log('updated', 'Communication', "Event \"{$event->title}\" was updated.", $event, $original, $data);

        return back()->with('status', 'Event updated.');
    }

    /**
     * Cancelling and deleting are different things, and both are offered.
     *
     * An event families have already been told about should be *cancelled*, so
     * it stays on the calendar saying so; deleting it makes the school look
     * like it never happened while parents still have it in their diary.
     * Deleting is for the one entered by mistake.
     */
    public function cancel(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $event->update(['status' => 'cancelled']);

        $audit->log('updated', 'Communication', "Event \"{$event->title}\" was cancelled.", $event);

        return back()->with('status', "\"{$event->title}\" is marked cancelled. It stays on the calendar so families see it was called off.");
    }

    public function destroy(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $title = $event->title;

        $event->delete();

        $audit->log('deleted', 'Communication', "Event \"{$title}\" was deleted.", null);

        return back()->with('status', "\"{$title}\" was removed from the calendar.");
    }

    protected function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($request->user()->hasPermission('events.manage'), 403);

        // The global scope already filters the binding; this fails closed if it
        // is ever removed (section 59).
        abort_unless($event->school_id === $request->user()->school_id, 403);
    }
}
