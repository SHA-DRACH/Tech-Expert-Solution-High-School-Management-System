<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The notification centre (spec section 46).
 *
 * Notifications belong to a user, not to a school, so there is no tenant scope
 * to apply here: a user only ever sees their own.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->trim()->toString();

        $query = $request->user()->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'filter' => $filter,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $entry = $request->user()->notifications()->findOrFail($notification);

        $entry->markAsRead();

        // Follow the notification through to whatever it is about.
        $url = $entry->data['url'] ?? null;

        return $url ? redirect()->to($url) : back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return back()->with('status', 'Notification removed.');
    }
}
