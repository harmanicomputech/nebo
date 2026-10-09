<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    /**
     * What's new since the page loaded or the last check (D75): the unread
     * count for the bells and the newest notifications for pop-ups. Polled
     * every few seconds by open pages.
     */
    public function poll(Request $request): JsonResponse
    {
        $since = rescue(fn () => Carbon::parse((string) $request->query('since')), now()->subMinute(), false);
        $user = $request->user();

        $items = $user->notifications()->where('created_at', '>=', $since)->latest()->limit(5)->get()
            ->reverse()->values()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'level' => $n->data['level'] ?? 'info',
                'url' => route('app.notifications.open', $n->id),
            ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            // A little overlap so nothing created in the same second is missed; the page skips ids it has shown.
            'now' => now()->subSeconds(3)->toIso8601String(),
            'items' => $items,
        ])->header('Cache-Control', 'no-store');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
