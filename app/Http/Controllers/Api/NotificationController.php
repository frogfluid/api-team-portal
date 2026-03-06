<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Return notifications as a flat array matching Swift [NotificationData].
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->take(50)
            ->get()
            ->map(fn($n) => [
                'id' => crc32($n->id), // Convert UUID to int for Swift
                'user_id' => (int) $n->notifiable_id,
                'type' => data_get($n->data, 'type', 'system'),
                'title' => data_get($n->data, 'title', ''),
                'message' => data_get($n->data, 'message', ''),
                'related_id' => data_get($n->data, 'related_id'),
                'is_read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json($notifications->values());
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        // Try both exact id and crc32-mapped id
        $notification = $request->user()->notifications()->find($id);

        if (!$notification) {
            // Try matching by crc32
            $notification = $request->user()->notifications()->get()->first(
                fn($n) => crc32($n->id) === (int) $id
            );
        }

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * POST /api/notifications/broadcast — Send a broadcast notification.
     * Extracted from inline route closure. Currently a stub.
     */
    public function broadcast(Request $request): JsonResponse
    {
        // TODO: Implement actual broadcast logic (push notifications, etc.)
        return response()->json(['message' => 'Broadcast sent.']);
    }
}
