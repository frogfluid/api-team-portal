<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class RealtimeController extends Controller
{
    public function stream(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $channelIds = $this->visibleChannelIds($user);
        $since = $request->query('since');
        $cursor = null;
        if (!empty($since)) {
            try {
                $cursor = Carbon::parse($since);
            } catch (\Throwable $e) {
                $cursor = null;
            }
        }

        @set_time_limit(0);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');

        return response()->stream(function () use ($user, $channelIds, $cursor) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @ob_implicit_flush(true);

            $lastSeen = $cursor;
            $startTime = microtime(true);

            while (!connection_aborted()) {
                $query = Message::whereIn('channel_id', $channelIds)
                    ->with(['user:id,name,role,avatar_path', 'attachments', 'replyTo.user:id,name', 'channel.users:id'])
                    ->orderBy('updated_at');

                if ($lastSeen) {
                    $query->where('updated_at', '>', $lastSeen);
                }

                $messages = $query->limit(200)->get();

                foreach ($messages as $msg) {
                    $payload = $this->transformMessageForApi($msg, $user);
                    $this->sendEvent('message', $payload, $msg->id);
                    $lastSeen = $msg->updated_at;
                }

                $this->sendEvent('heartbeat', ['ts' => now()->toIso8601String()]);

                @flush();

                if ((microtime(true) - $startTime) > 55) {
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function visibleChannelIds(User $user)
    {
        $query = Channel::where('type', 'public');
        if (!$user->isExecutive()) {
            $query->where('name', '!=', 'Executive Board');
        }
        $channels = $query->orderBy('name')->get();

        $privateChannels = $user->channels()->where('type', 'private')->get();
        return $channels->pluck('id')->merge($privateChannels->pluck('id'))->unique()->values();
    }

    private function sendEvent(string $event, array $data, ?int $id = null): void
    {
        echo "event: {$event}\n";
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "data: {$json}\n\n";
    }

    private function transformMessageForApi(Message $message, User $viewer): array
    {
        $channel = $message->channel;
        $recipientId = null;
        if ($channel && $channel->type === 'private') {
            $otherUser = $channel->users()->where('users.id', '!=', $message->user_id)->first();
            $recipientId = $otherUser?->id;
        }

        $isRead = false;
        if ($message->user_id === $viewer->id) {
            $isRead = true;
        } elseif ($channel) {
            $pivot = $channel->users()->where('users.id', $viewer->id)->first()?->pivot;
            $lastRead = $pivot?->last_read_at;
            if ($lastRead) {
                $isRead = $message->created_at <= $lastRead;
            }
        }

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
            'created_at' => $message->created_at?->toIso8601String(),
            'is_read' => $isRead,
            'attachments' => $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_name' => basename($attachment->path),
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => (int) $attachment->size_bytes,
                    'url' => Storage::disk($attachment->disk ?? 'public')->url($attachment->path),
                ];
            })->values()->toArray(),
        ];
    }
}
