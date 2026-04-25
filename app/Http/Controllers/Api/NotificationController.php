<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\BroadcastNotification;
use App\Services\ApnsService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Return notifications as a flat array matching Swift [NotificationData].
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->notifications()
            ->latest();

        // Incremental sync support
        if (! $request->boolean('force_full') && $request->filled('updated_since')) {
            try {
                $since = \Carbon\Carbon::parse((string) $request->updated_since);
                $query->where('updated_at', '>', $since);
            } catch (\Exception $e) {
                // Malformed timestamp — fall through to full list.
            }
        }

        $notifications = $query
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
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $title = $data['title'];
        $message = $data['message'];

        $users = User::where('is_active', true)->get();
        foreach ($users as $user) {
            $user->notify(new BroadcastNotification($title, $message));
        }

        try {
            app(ApnsService::class)->sendToUsers($users->all(), [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body' => mb_strimwidth($message, 0, 180, '…'),
                    ],
                    'sound' => 'default',
                ],
                'type' => 'broadcast',
            ], 'broadcast');
        } catch (\Throwable $e) {
            \Log::warning('APNs broadcast failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Broadcast sent.']);
    }

    /**
     * POST /api/notifications/test-push — Send a test push to current user.
     */
    public function testPush(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isManagement') || !$user->isManagement()) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        try {
            app(ApnsService::class)->sendToUsers([$user], [
                'aps' => [
                    'alert' => [
                        'title' => $data['title'],
                        'body' => mb_strimwidth($data['message'], 0, 180, '…'),
                    ],
                    'sound' => 'default',
                ],
                'type' => 'test',
            ], 'test-push-' . $user->id);
        } catch (\Throwable $e) {
            \Log::warning('APNs test failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Test push failed.'], 500);
        }

        return response()->json(['message' => 'Test push sent.']);
    }

}