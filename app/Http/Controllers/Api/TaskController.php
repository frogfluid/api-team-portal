<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\TaskOwnerTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tab = $request->get('tab', 'my');

        $q = Task::query()
            ->with(['owner:id,name', 'participants:id,name', 'creator:id,name'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');

        if ($tab === 'my') {
            $q->mine($user->id);
        }

        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $q->whereIn('status', $statuses);
        }

        if ($request->filled('priority')) {
            $q->where('priority', $request->priority);
        }

        if ($request->filled('q') || $request->filled('search')) {
            $keyword = trim($request->q ?? $request->search);
            $q->where(function ($qq) use ($keyword) {
                $qq->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Incremental sync: only return tasks updated after a given timestamp
        if ($request->filled('updated_since')) {
            $q->where('updated_at', '>', $request->updated_since);
        }

        $tasks = $q->paginate($request->integer('per_page', 20));

        return response()->json([
            'tasks' => $tasks->getCollection()->map(fn($t) => $this->transformTask($t))->values(),
            'total' => $tasks->total(),
        ]);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load([
            'owner:id,name',
            'creator:id,name',
            'participants:id,name',
            'ownerHistories.fromOwner:id,name',
            'ownerHistories.toOwner:id,name',
            'ownerHistories.changer:id,name',
        ]);

        return response()->json($this->transformTask($task, true));
    }

    /**
     * Map iOS/Swift enum values to web DB values.
     */
    private function normalizeStatus(?string $status): ?string
    {
        return match ($status) {
            'pending' => 'opened',
            'completed' => 'done',
            default => $status,
        };
    }

    private function normalizePriority(?string $priority): ?string
    {
        return match ($priority) {
            'medium' => 'normal',
            default => $priority,
        };
    }

    public function store(Request $request): JsonResponse
    {
        // Accept both Swift (assignee_id/due_date) and web (owner_id/due_at) field names
        if ($request->has('assignee_id') && !$request->has('owner_id')) {
            $request->merge(['owner_id' => $request->assignee_id]);
        }
        if ($request->has('due_date') && !$request->has('due_at')) {
            $request->merge(['due_at' => $request->due_date]);
        }
        // Normalize enum values
        if ($request->has('status')) {
            $request->merge(['status' => $this->normalizeStatus($request->status)]);
        }
        if ($request->has('priority')) {
            $request->merge(['priority' => $this->normalizePriority($request->priority)]);
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:opened,in_progress,on_hold,blocked,done'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'location' => $request->location,
            'status' => $request->status ?? 'opened',
            'priority' => $request->priority ?? 'normal',
            'progress' => (int) ($request->progress ?? 0),
            'due_at' => $request->due_at,
            'created_by' => $request->user()->id,
            'owner_id' => $request->owner_id,
            'last_activity_at' => now(),
        ]);

        if (!empty($request->participant_ids)) {
            $task->participants()->sync(array_unique($request->participant_ids));
        }

        if ($task->owner_id && $task->owner_id !== $request->user()->id) {
            $owner = User::find($task->owner_id);
            if ($owner) {
                $owner->notify(new TaskAssigned($task, $request->user(), "{$request->user()->name} assigned you a new task: {$task->title}"));
            }
        }

        $task->load(['owner:id,name', 'creator:id,name', 'participants:id,name']);

        return response()->json($this->transformTask($task), 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        // Accept both Swift and web field names
        if ($request->has('assignee_id') && !$request->has('owner_id')) {
            $request->merge(['owner_id' => $request->assignee_id]);
        }
        if ($request->has('due_date') && !$request->has('due_at')) {
            $request->merge(['due_at' => $request->due_date]);
        }
        if ($request->has('status')) {
            $request->merge(['status' => $this->normalizeStatus($request->status)]);
        }
        if ($request->has('priority')) {
            $request->merge(['priority' => $this->normalizePriority($request->priority)]);
        }

        $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:opened,in_progress,on_hold,blocked,done'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'participant_ids' => ['nullable', 'array'],
        ]);

        $task->update($request->only(['title', 'description', 'location', 'status', 'priority', 'progress', 'due_at', 'owner_id']));

        if ($request->has('participant_ids')) {
            $task->participants()->sync(array_unique($request->participant_ids ?? []));
        }

        $task->touchActivity();
        $task->load(['owner:id,name', 'creator:id,name', 'participants:id,name']);

        return response()->json($this->transformTask($task));
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $task->load('participants');
        $totalParticipants = $task->participants->count();

        if ($totalParticipants > 0) {
            $participant = $task->participants->firstWhere('id', $user->id);
            if ($participant) {
                if (!$participant->pivot->completed_at) {
                    $task->participants()->updateExistingPivot($user->id, ['completed_at' => now()]);
                }
            } else {
                $task->update(['status' => 'done', 'progress' => 100]);
                $task->touchActivity();
                return response()->json(['success' => true, 'progress' => 100, 'status' => 'done']);
            }

            $task->refresh();
            $completedCount = $task->participants()->whereNotNull('task_participants.completed_at')->count();
            $progress = (int) round(($completedCount / $totalParticipants) * 100);

            if ($completedCount >= $totalParticipants) {
                $task->update(['status' => 'done', 'progress' => 100]);
            } else {
                $task->update(['progress' => $progress]);
            }

            $task->touchActivity();

            return response()->json([
                'success' => true,
                'progress' => $task->progress,
                'status' => $task->status,
                'completed_count' => $completedCount,
                'total_participants' => $totalParticipants,
            ]);
        }

        $task->update(['status' => 'done', 'progress' => 100]);
        $task->touchActivity();

        return response()->json(['success' => true, 'progress' => 100, 'status' => 'done']);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        // Only creator, owner, or admin can delete
        if ($task->creator_id !== $user->id && $task->owner_id !== $user->id && !$user->canManageUsers()) {
            abort(403, 'Unauthorized to delete this task.');
        }

        $task->participants()->detach();
        $task->messages()->delete();
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function messages(Task $task): JsonResponse
    {
        $messages = $task->messages()
            ->with(['user:id,name', 'attachments'])
            ->orderBy('created_at')
            ->get();

        return response()->json($messages->map(fn($m) => $this->transformTaskMessage($m))->values());
    }

    public function storeMessage(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:comment,progress_update,status_change'],
            'body' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $meta = null;
        if ($request->type === 'progress_update') {
            $progress = (int) $request->progress;
            $meta = ['progress' => (string) $progress];
            $task->update(['progress' => $progress]);
        }

        $message = TaskMessage::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'body' => $request->body,
            'meta' => $meta,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("tasks/{$task->id}", 'public');
                TaskAttachment::create([
                    'task_id' => $task->id,
                    'message_id' => $message->id,
                    'uploaded_by' => $request->user()->id,
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ]);
            }
        }

        $message->load(['user:id,name', 'attachments']);

        return response()->json($this->transformTaskMessage($message), 201);
    }

    public function transferOwner(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'to_owner_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ]);

        $service = app(TaskOwnerTransferService::class);
        $service->transfer(
            task: $task,
            toOwnerId: (int) $request->to_owner_id,
            changedByUserId: $request->user()->id,
            note: $request->note
        );

        $task->refresh()->load([
            'owner:id,name',
            'creator:id,name',
            'participants:id,name',
            'ownerHistories.fromOwner:id,name',
            'ownerHistories.toOwner:id,name',
            'ownerHistories.changer:id,name',
        ]);

        return response()->json($this->transformTask($task, true));
    }

    private function transformTask(Task $task, bool $includeDetails = false): array
    {
        $data = [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'location' => $task->location,
            'status' => $task->status,
            'priority' => $task->priority,
            'progress' => $task->progress,
            'assignee_id' => $task->owner_id,
            'assignee_name' => $task->owner?->name,
            'creator_id' => $task->created_by,
            'creator_name' => $task->creator?->name,
            'due_date' => $task->due_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'tags' => null,
            'estimated_hours' => null,
            'actual_hours' => null,
        ];

        if ($task->relationLoaded('participants')) {
            $data['participants'] = $task->participants->map(fn($p) => [
                'id' => (int) ($p->pivot->id ?? $p->id),
                'task_id' => $task->id,
                'user_id' => $p->id,
                'user_name' => $p->name,
                'role' => $p->pivot->role ?? 'collaborator',
                'completed_at' => $p->pivot->completed_at,
            ])->values()->toArray();
        }

        if ($includeDetails && $task->relationLoaded('ownerHistories')) {
            $data['owner_histories'] = $task->ownerHistories->map(fn($h) => [
                'id' => $h->id,
                'task_id' => $h->task_id,
                'from_owner_id' => $h->from_owner_id,
                'from_owner_name' => $h->fromOwner?->name,
                'to_owner_id' => $h->to_owner_id,
                'to_owner_name' => $h->toOwner?->name,
                'changed_by' => $h->changed_by,
                'changed_by_name' => $h->changer?->name,
                'note' => $h->note,
                'changed_at' => $h->changed_at?->toIso8601String(),
            ])->values()->toArray();
        }

        return $data;
    }

    private function transformTaskMessage(TaskMessage $message): array
    {
        $meta = $message->meta;
        $metaStrings = null;
        if (is_array($meta)) {
            $metaStrings = [];
            foreach ($meta as $k => $v) {
                $metaStrings[(string) $k] = (string) $v;
            }
        }

        return [
            'id' => $message->id,
            'task_id' => $message->task_id,
            'channel_id' => null,
            'recipient_id' => null,
            'sender_id' => $message->user_id,
            'sender_name' => $message->user?->name ?? '',
            'content' => $message->body ?? '',
            'message_type' => $message->type,
            'meta' => $metaStrings,
            'reply_to_id' => null,
            'revoked_at' => null,
            'revoked_by' => null,
            'mentioned_user_ids' => null,
            'created_at' => $message->created_at?->toIso8601String(),
            'is_read' => true,
            'attachments' => $message->relationLoaded('attachments')
                ? $message->attachments->map(fn($a) => [
                    'id' => $a->id,
                    'file_name' => basename($a->path),
                    'original_name' => $a->original_name,
                    'mime_type' => $a->mime_type,
                    'size' => (int) $a->size_bytes,
                    'url' => Storage::disk($a->disk ?? 'public')->url($a->path),
                ])->values()->toArray()
                : null,
        ];
    }
}
