<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest('created_at')
            ->paginate(20);

        $unreadCount = $request->user()
            ->notifications()
            ->unread()
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403);
        $notification->update(['read_at' => now()]);
        return response()->json($notification);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);
        return response()->json(['message' => 'All marked as read.']);
    }
}
