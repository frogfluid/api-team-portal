<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\ApnsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        // Auto-create a company-wide channel.
        $general = Channel::firstOrCreate(
            ['name' => 'General HQ'],
            [
                'description' => 'Global firm-wide discussion',
                'type' => 'public',
            ]
        );

        if ($request->user()->isExecutive()) {
            Channel::firstOrCreate(
                ['name' => 'Executive Board'],
                [
                    'description' => 'Top level management discussion',
                    'type' => 'public',
                ]
            );
        }

        $channels = $this->withUnreadCounts($request->user());
        $users = User::where('is_active', true)->where('id', '!=', auth()->id())->orderBy('name')->get();

        return view('app.chat.index', [
            'channels' => $channels,
            'activeChannel' => $general,
            'users' => $users,
            'dmUnreadCounts' => $this->dmUnreadCounts($request->user(), $users),
        ]);
    }

    public function directMessage(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('app.chat.index');
        }

        $id1 = min(auth()->id(), $user->id);
        $id2 = max(auth()->id(), $user->id);
        $name = "dm_{$id1}_{$id2}";

        $channel = Channel::firstOrCreate(
            ['name' => $name],
            ['description' => 'Direct Message', 'type' => 'private']
        );

        $channel->users()->syncWithoutDetaching([$id1, $id2]);

        $channels = $this->withUnreadCounts($request->user());
        $users = User::where('is_active', true)->where('id', '!=', auth()->id())->orderBy('name')->get();

        return view('app.chat.index', [
            'channels' => $channels,
            'activeChannel' => $channel,
            'users' => $users,
            'dmUser' => $user,
            'dmUnreadCounts' => $this->dmUnreadCounts($request->user(), $users),
        ]);
    }

    public function show(Request $request, Channel $channel)
    {
        $this->ensureChannelAccess($request, $channel);

        $dmUser = null;
        if ($channel->type === 'private') {
            $dmUser = $channel->users()->where('users.id', '!=', auth()->id())->first();
        }

        $channels = $this->withUnreadCounts($request->user());

        // Update read receipt for this active channel
        $channel->users()->syncWithoutDetaching([
            $request->user()->id => ['last_read_at' => now()]
        ]);

        $users = User::where('is_active', true)->where('id', '!=', auth()->id())->orderBy('name')->get();

        return view('app.chat.index', [
            'channels' => $channels,
            'activeChannel' => $channel,
            'users' => $users,
            'dmUser' => $dmUser,
            'dmUnreadCounts' => $this->dmUnreadCounts($request->user(), $users),
        ]);
    }

    public function getMessages(Request $request, Channel $channel): JsonResponse
    {
        $this->ensureChannelAccess($request, $channel);

        // Update read timestamp since user is fetching messages
        $channel->users()->syncWithoutDetaching([
            $request->user()->id => ['last_read_at' => now()]
        ]);

        $messages = $channel->messages()
            ->with(['user:id,name,role,avatar_path', 'attachments', 'replyTo.user:id,name'])
            ->oldest()
            ->limit(200)
            ->get();

        return response()->json(
            $messages->map(fn(Message $message) => $this->transformMessage($message))->values()
        );
    }

    public function storeMessage(Request $request, Channel $channel): JsonResponse
    {
        $this->ensureChannelAccess($request, $channel);

        $request->validate([
            'content' => ['nullable', 'string', 'max:4000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:20480'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $content = trim((string) $request->input('content', ''));
        $files = $request->file('files', []);
        $mentionIds = collect($request->input('mention_ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        if ($content === '' && count($files) === 0) {
            return response()->json([
                'message' => 'Message content or at least one attachment is required.',
            ], 422);
        }

        $message = $channel->messages()->create([
            'user_id' => auth()->id(),
            'content' => $content,
            'reply_to_id' => $request->input('reply_to_id'),
            'mentioned_user_ids' => $mentionIds->all(),
        ]);

        $channel->users()->syncWithoutDetaching([
            $request->user()->id => ['last_read_at' => now()]
        ]);

        foreach ($files as $file) {
            $dir = 'attachments/chat/' . $channel->id . '/' . auth()->id() . '/' . now()->format('Ymd');
            $path = $file->store($dir, 'public');

            Attachment::create([
                'attachable_type' => Message::class,
                'attachable_id' => $message->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $message->load(['user:id,name,role,avatar_path', 'attachments', 'replyTo.user:id,name']);

        broadcast(new \App\Events\MessageSent($message))->toOthers();

        try {
            $targets = $this->resolvePushTargets($channel, $request->user());
            $body = trim((string) $message->content);
            if ($body === '') {
                $body = $message->attachments()->exists() ? 'Sent an attachment' : 'New message';
            }

            app(ApnsService::class)->sendToUsers($targets, [
                'aps' => [
                    'alert' => [
                        'title' => $channel->name ?? 'New message',
                        'body' => mb_strimwidth($body, 0, 180, '…'),
                    ],
                    'sound' => 'default',
                ],
                'type' => 'chat',
                'message_id' => $message->id,
                'channel_id' => $message->channel_id,
                'sender_id' => $message->user_id,
            ], 'chat-' . $channel->id);
        } catch (\Throwable $e) {
            \Log::warning('APNs send failed', ['error' => $e->getMessage()]);
        }

        return response()->json($this->transformMessage($message));
    }

    public function revokeMessage(Request $request, Message $message): JsonResponse
    {
        $channel = $message->channel;
        $this->ensureChannelAccess($request, $channel);

        if ((int) $message->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only revoke your own messages.');
        }

        if (!$message->revoked_at) {
            $message->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
            ])->save();
        }

        $message->load(['user:id,name,role,avatar_path', 'attachments']);

        return response()->json([
            'success' => true,
            'data' => 'Message revoked successfully',
        ]);
    }

    public function previewAttachment(Request $request, Attachment $attachment)
    {
        $message = $attachment->attachable;
        if (!$message instanceof Message) {
            abort(404);
        }

        if ($message->revoked_at) {
            abort(410, 'Attachment is unavailable because the message was revoked.');
        }

        $this->ensureChannelAccess($request, $message->channel);

        $disk = Storage::disk($attachment->disk);
        if (!$disk->exists($attachment->path)) {
            abort(404);
        }

        $mime = $attachment->mime_type ?: ($disk->mimeType($attachment->path) ?: 'application/octet-stream');
        $stream = $disk->readStream($attachment->path);
        if ($stream === false) {
            abort(404);
        }

        $filename = str_replace(['"', "\n", "\r"], '', $attachment->original_name ?: basename($attachment->path));

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $message = $attachment->attachable;
        if (!$message instanceof Message) {
            abort(404);
        }

        if ($message->revoked_at) {
            abort(410, 'Attachment is unavailable because the message was revoked.');
        }

        $this->ensureChannelAccess($request, $message->channel);

        $disk = Storage::disk($attachment->disk);
        if (!$disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    private function ensureChannelAccess(Request $request, Channel $channel): void
    {
        if ($channel->name === 'Executive Board' && !$request->user()->isExecutive()) {
            abort(403, 'Unauthorized access to Executive Board');
        }

        if ($channel->type === 'private' && !$channel->users()->where('users.id', auth()->id())->exists()) {
            abort(403, 'Unauthorized access to private channel');
        }
    }

    private function visibleChannelsFor(User $user)
    {
        $query = Channel::where('type', 'public');
        if (!$user->isExecutive()) {
            $query->where('name', '!=', 'Executive Board');
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Batch-load unread counts for all visible channels.
     * Eliminates N+1 by doing 2 queries instead of 2N.
     */
    private function withUnreadCounts(User $user)
    {
        $channels = $this->visibleChannelsFor($user);
        $channelIds = $channels->pluck('id');

        // Get last_read_at for all channels in one query
        $readTimestamps = \DB::table('channel_user')
            ->where('user_id', $user->id)
            ->whereIn('channel_id', $channelIds)
            ->pluck('last_read_at', 'channel_id');

        // Count unread messages per channel in one query
        $totalCounts = \DB::table('messages')
            ->whereIn('channel_id', $channelIds)
            ->selectRaw('channel_id, COUNT(*) as total')
            ->groupBy('channel_id')
            ->pluck('total', 'channel_id');

        foreach ($channels as $c) {
            $lastRead = $readTimestamps->get($c->id);
            if ($lastRead) {
                $c->unread_count = \DB::table('messages')
                    ->where('channel_id', $c->id)
                    ->where('created_at', '>', $lastRead)
                    ->count();
            } else {
                $c->unread_count = $totalCounts->get($c->id, 0);
            }
        }

        return $channels;
    }

    /**
     * Batch-load DM unread counts for the sidebar.
     * Returns array keyed by user ID with unread count.
     */
    private function dmUnreadCounts(User $authUser, $users): array
    {
        $counts = [];
        $dmNames = [];
        $userMap = [];

        foreach ($users as $u) {
            $id1 = min($authUser->id, $u->id);
            $id2 = max($authUser->id, $u->id);
            $name = "dm_{$id1}_{$id2}";
            $dmNames[] = $name;
            $userMap[$name] = $u->id;
        }

        if (empty($dmNames)) {
            return $counts;
        }

        // Load all DM channels + their last_read_at for auth user in 2 queries
        $dmChannels = Channel::whereIn('name', $dmNames)
            ->where('type', 'private')
            ->get()
            ->keyBy('name');

        if ($dmChannels->isEmpty()) {
            return $counts;
        }

        $channelIds = $dmChannels->pluck('id');
        $readTimestamps = \DB::table('channel_user')
            ->where('user_id', $authUser->id)
            ->whereIn('channel_id', $channelIds)
            ->pluck('last_read_at', 'channel_id');

        foreach ($dmChannels as $name => $ch) {
            $lastRead = $readTimestamps->get($ch->id);
            $userId = $userMap[$name] ?? null;
            if (!$userId)
                continue;

            if ($lastRead) {
                $counts[$userId] = \DB::table('messages')
                    ->where('channel_id', $ch->id)
                    ->where('created_at', '>', $lastRead)
                    ->count();
            } else {
                $counts[$userId] = \DB::table('messages')
                    ->where('channel_id', $ch->id)
                    ->count();
            }
        }

        return $counts;
    }

    private function transformMessage(Message $message): array
    {
        $user = $message->user;
        $attachments = $message->revoked_at
            ? collect()
            : $message->attachments;

        return [
            'id' => $message->id,
            'channel_id' => $message->channel_id,
            'user_id' => $message->user_id,
            'content' => $message->content,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
            'revoked_at' => $message->revoked_at,
            'is_revoked' => (bool) $message->revoked_at,
            'reply_to_id' => $message->reply_to_id,
            'reply_to' => $message->replyTo && !$message->replyTo->revoked_at ? [
                'id' => $message->replyTo->id,
                'content' => $message->replyTo->content,
                'user_name' => $message->replyTo->user?->name,
            ] : null,
            'mentions' => !empty($message->mentioned_user_ids)
                ? User::whereIn('id', $message->mentioned_user_ids)->get(['id', 'name'])->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
                : [],
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name ?? 'Former User',
                'role' => $user?->role?->value ?? (string) $user?->role,
                'role_label' => $user?->role_label ?? 'Member',
                'avatar_url' => $user?->avatar_url,
            ],
            'attachments' => $attachments->map(function (Attachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'is_image' => str_starts_with((string) $attachment->mime_type, 'image/'),
                    'is_pdf' => ($attachment->mime_type ?? '') === 'application/pdf',
                    'preview_url' => route('app.chat.attachments.preview', $attachment),
                    'download_url' => route('app.chat.attachments.download', $attachment),
                ];
            })->values(),
        ];
    }

    // MARK: - iOS App API Compatibility Handlers

    public function getAppMessages(Request $request): JsonResponse
    {
        $user = $request->user();
        $channels = $this->visibleChannelsFor($user);

        // Also include all private channels the user is part of
        $privateChannels = $user->channels()->where('type', 'private')->get();
        $allChannelIds = $channels->pluck('id')->merge($privateChannels->pluck('id'))->unique();

        $query = Message::whereIn('channel_id', $allChannelIds)
            ->with(['user:id,name,role,avatar_path', 'attachments', 'replyTo.user:id,name'])
            ->latest();

        // Incremental sync: only return messages updated after a given timestamp
        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->updated_since);
        }

        // Pagination support (default 100, max 500)
        $limit = min($request->integer('per_page', 100), 500);
        $messages = $query->limit($limit)->get();

        $mapped = $messages->map(function ($msg) use ($user) {
            $channel = $msg->channel;
            $recipientId = null;
            if ($channel && $channel->type === 'private') {
                $otherUser = $channel->users()->where('users.id', '!=', $msg->user_id)->first();
                $recipientId = $otherUser?->id;
            }

            // Check if read
            $lastRead = $channel ? $channel->users()->where('users.id', $user->id)->first()?->pivot?->last_read_at : null;
            $isRead = $lastRead ? ($msg->created_at <= $lastRead) : false;
            if ($msg->user_id === $user->id)
                $isRead = true;

            return $this->transformMessageForApi($msg, $recipientId, $isRead);
        });

        return response()->json($mapped->values());
    }

    public function storeAppMessage(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['nullable', 'string'],
            'channelId' => ['nullable', 'string'],
            'channel_id' => ['nullable', 'string'],
            'recipientId' => ['nullable', 'integer'],
            'recipient_id' => ['nullable', 'integer'],
            'attachments.*' => ['nullable', 'file', 'max:51200'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer', 'exists:attachments,id'],
        ]);

        $channelId = $request->input('channelId') ?? $request->input('channel_id');
        $recipientId = $request->input('recipientId') ?? $request->input('recipient_id');
        $content = $request->input('content', '');
        $files = $request->file('attachments') ?? [];
        $attachmentIds = $request->input('attachment_ids') ?? $request->input('attachmentIds') ?? [];
        $channel = null;

        if ($content === '' && count($files) === 0 && count($attachmentIds) === 0) {
            return response()->json([
                'message' => 'Message content or at least one attachment is required.',
            ], 422);
        }

        if ($channelId) {
            $channel = Channel::find($channelId);
        } elseif ($recipientId) {
            $id1 = min(auth()->id(), $recipientId);
            $id2 = max(auth()->id(), $recipientId);
            $name = "dm_{$id1}_{$id2}";

            $channel = Channel::firstOrCreate(
                ['name' => $name],
                ['description' => 'Direct Message', 'type' => 'private']
            );
            $channel->users()->syncWithoutDetaching([$id1, $id2]);
        }

        if (!$channel) {
            return response()->json(['message' => 'Channel or recipient required'], 422);
        }

        $this->ensureChannelAccess($request, $channel);

        $replyToId = $request->input('reply_to_id') ?? $request->input('replyToId');
        $mentionIds = collect($request->input('mentioned_user_ids') ?? $request->input('mentionedUserIds') ?? [])
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        $message = $channel->messages()->create([
            'user_id' => auth()->id(),
            'content' => $content,
            'reply_to_id' => $replyToId,
            'mentioned_user_ids' => $mentionIds->all(),
        ]);

        $channel->users()->syncWithoutDetaching([
            $request->user()->id => ['last_read_at' => now()]
        ]);

        if (!empty($attachmentIds)) {
            \App\Models\Attachment::whereIn('id', $attachmentIds)
                ->where('uploaded_by', auth()->id())
                ->update([
                    'attachable_type' => Message::class,
                    'attachable_id' => $message->id,
                ]);
        }

        foreach ($files as $file) {
            $dir = 'attachments/chat/' . $channel->id . '/' . auth()->id() . '/' . now()->format('Ymd');
            $path = $file->store($dir, 'public');

            Attachment::create([
                'attachable_type' => Message::class,
                'attachable_id' => $message->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $message->load(['user:id,name,role,avatar_path', 'attachments', 'replyTo.user:id,name']);

        try {
            broadcast(new \App\Events\MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            // Broadcasting is optional — don't crash if event class is missing
        }

        try {
            $targets = $this->resolvePushTargets($channel, $request->user());
            $body = trim((string) $message->content);
            if ($body === '') {
                $body = $message->attachments()->exists() ? 'Sent an attachment' : 'New message';
            }

            app(ApnsService::class)->sendToUsers($targets, [
                'aps' => [
                    'alert' => [
                        'title' => $channel->name ?? 'New message',
                        'body' => mb_strimwidth($body, 0, 180, '…'),
                    ],
                    'sound' => 'default',
                ],
                'type' => 'chat',
                'message_id' => $message->id,
                'channel_id' => $message->channel_id,
                'sender_id' => $message->user_id,
            ], 'chat-' . $channel->id);
        } catch (\Throwable $e) {
            \Log::warning('APNs send failed', ['error' => $e->getMessage()]);
        }

        return response()->json($this->transformMessageForApi($message, $recipientId, true));
    }

    public function markAppMessageRead(Request $request, $id): JsonResponse
    {
        $message = Message::find($id);
        if ($message && $message->channel) {
            $message->channel->users()->syncWithoutDetaching([
                $request->user()->id => ['last_read_at' => now()]
            ]);
        }
        return response()->json(['status' => 'success']);
    }

    public function markAllAppMessagesRead(Request $request): JsonResponse
    {
        $channels = $request->user()->channels;
        foreach ($channels as $channel) {
            $channel->users()->syncWithoutDetaching([
                $request->user()->id => ['last_read_at' => now()]
            ]);
        }
        return response()->json(['status' => 'success']);
    }

    /**
     * Transform a Message model into the format matching Swift MessageData struct.
     * Uses snake_case keys compatible with Swift's convertFromSnakeCase decoder.
     */
    private function resolvePushTargets(\App\Models\Channel $channel, \App\Models\User $sender)
    {
        if ($channel->type === 'public') {
            $query = \App\Models\User::where('is_active', true)->where('id', '!=', $sender->id);
            if ($channel->name === 'Executive Board') {
                $targets = $query->get()->filter(fn($u) => $u->isExecutive())->values();
                return $targets;
            }
            return $query->get();
        }

        return $channel->users()->where('users.id', '!=', $sender->id)->get();
    }

    private function transformMessageForApi(Message $message, ?int $recipientId = null, bool $isRead = false): array
    {
        $user = $message->user;
        $attachments = $message->revoked_at ? collect() : $message->attachments;

        return [
            'id' => $message->id,
            'task_id' => null,
            'channel_id' => (string) $message->channel_id,
            'recipient_id' => $recipientId,
            'sender_id' => $message->user_id,
            'sender_name' => $user?->name ?? 'Former User',
            'content' => $message->content ?? '',
            'message_type' => 'chat',
            'meta' => null,
            'reply_to_id' => $message->reply_to_id,
            'revoked_at' => $message->revoked_at?->toIso8601String(),
            'revoked_by' => $message->revoked_by,
            'mentioned_user_ids' => !empty($message->mentioned_user_ids) ? $message->mentioned_user_ids : null,
            'pinned_at' => $message->pinned_at?->toIso8601String(),
            'pinned_by_user_id' => $message->pinned_by_user_id,
            'link_metadata' => $message->link_metadata,
            'created_at' => $message->created_at?->toIso8601String(),
            'is_read' => $isRead,
            'attachments' => $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_name' => basename($attachment->path),
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => (int) $attachment->size_bytes,
                    'url' => \Illuminate\Support\Facades\Storage::disk($attachment->disk ?? 'public')->url($attachment->path),
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * GET /api/channels — Return all visible channels with unread counts (for iOS app).
     * Extracted from inline route closure for maintainability.
     */
    public function getAppChannels(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Channel::where('type', 'public');
        if (!$user->isExecutive()) {
            $query->where('name', '!=', 'Executive Board');
        }
        $channels = $query->orderBy('name')->get();

        // Also include user's private channels (DMs)
        $privateChannels = $user->channels()->where('type', 'private')->get();

        $all = $channels->merge($privateChannels)->unique('id')->values();

        return response()->json($all->map(function ($c) use ($user) {
            $lastRead = $c->users()->where('users.id', $user->id)->first()?->pivot?->last_read_at;
            return [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'type' => $c->type,
                'unread_count' => $lastRead
                    ? $c->messages()->where('created_at', '>', $lastRead)->count()
                    : $c->messages()->count(),
            ];
        }));
    }
}