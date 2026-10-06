<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = ($filter === 'unread' ? $request->user()->unreadNotifications() : $request->user()->notifications())
            ->paginate(20)->withQueryString();

        return view('internal.notifications.index', ['notifications' => $notifications, 'filter' => $filter]);
    }

    /**
     * Marks one notification read and follows its link. Scoped to the
     * signed-in user, so another user's notification is a 404.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $url = $item->data['url'] ?? null;

        // Only follow links back into this application.
        return is_string($url) && str_starts_with($url, url('/'))
            ? redirect()->to($url)
            : redirect()->route('app.notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
