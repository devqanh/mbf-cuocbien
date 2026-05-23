<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Trang xem toàn bộ notification. */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    /** JSON cho dropdown bell — 10 cái mới nhất + unread count. */
    public function feed(Request $request): JsonResponse
    {
        $user   = $request->user();
        $items  = $user->notifications()->limit(10)->get();
        $unread = $user->unreadNotifications()->count();

        return response()->json([
            'unread'        => $unread,
            'notifications' => $items->map(fn ($n) => [
                'id'         => $n->id,
                'read'       => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
                'created_human' => $n->created_at?->diffForHumans(),
                'data'       => $n->data,
            ]),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Đã đánh dấu tất cả là đã đọc.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }
}
