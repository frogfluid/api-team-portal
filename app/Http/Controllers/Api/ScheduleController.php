<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleComment;
use App\Notifications\SystemMessageNotification;
use App\Notifications\WorkScheduleCommentMentioned;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $schedules = WorkSchedule::query()
            ->where('user_id', $user->id)
            ->with(['user:id,name', 'approver:id,name'])
            ->orderByDesc('start_at')
            ->limit(50)
            ->get();

        return response()->json($schedules->map(fn ($s) => $this->transformSchedule($s))->values());
    }

    public function show(WorkSchedule $workSchedule): JsonResponse
    {
        $workSchedule->load(['user:id,name', 'approver:id,name', 'comments.user:id,name']);

        return response()->json($this->transformSchedule($workSchedule));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:work,leave'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'all_day' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
            'leave_type' => ['nullable', 'string', 'in:annual,sick'],
        ]);

        $user = $request->user();

        $schedule = WorkSchedule::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'start_at' => Carbon::parse($request->start_at)->utc(),
            'end_at' => Carbon::parse($request->end_at)->utc(),
            'all_day' => $request->boolean('all_day'),
            'note' => $request->note,
            'leave_type' => $request->type === 'leave' ? ($request->leave_type ?? 'annual') : null,
            'status' => 'pending',
        ]);

        $schedule->load('user:id,name');

        return response()->json($this->transformSchedule($schedule), 201);
    }

    public function update(Request $request, WorkSchedule $workSchedule): JsonResponse
    {
        $this->authorize('update', $workSchedule);

        $request->validate([
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'all_day' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:work,leave'],
        ]);

        $workSchedule->update(array_filter($request->only(['start_at', 'end_at', 'all_day', 'note', 'type'])));

        $fresh = $workSchedule->fresh()->load(['user:id,name', 'approver:id,name']);

        return response()->json($this->transformSchedule($fresh));
    }

    public function destroy(Request $request, WorkSchedule $workSchedule): JsonResponse
    {
        $this->authorize('delete', $workSchedule);

        $workSchedule->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }

    public function comments(WorkSchedule $workSchedule): JsonResponse
    {
        $comments = $workSchedule->comments()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($comments->map(fn ($c) => $this->transformComment($c))->values());
    }

    public function storeComment(Request $request, WorkSchedule $workSchedule): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $workSchedule->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        $comment->load('user:id,name');

        return response()->json($this->transformComment($comment), 201);
    }

    private function transformSchedule(WorkSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'user_id' => $schedule->user_id,
            'user_name' => $schedule->user?->name,
            'schedule_type' => $schedule->type,
            'all_day' => (bool) $schedule->all_day,
            'start_at' => $schedule->start_at?->toIso8601String(),
            'end_at' => $schedule->end_at?->toIso8601String(),
            'break_minutes' => (int) ($schedule->break_minutes ?? 0),
            'status' => $schedule->status,
            'note' => $schedule->note,
            'repeat_group_id' => $schedule->repeat_group_id,
            'repeat_weeks' => null,
            'manager_comment' => $schedule->manager_comment,
            'manager_comment_by' => $schedule->manager_comment_by,
            'approved_by' => $schedule->approved_by,
            'approved_at' => $schedule->approved_at?->toIso8601String(),
            'created_at' => $schedule->created_at?->toIso8601String(),
        ];
    }

    private function transformComment(WorkScheduleComment $comment): array
    {
        return [
            'id' => $comment->id,
            'work_schedule_id' => $comment->work_schedule_id,
            'user_id' => $comment->user_id,
            'user_name' => $comment->user?->name,
            'body' => $comment->body,
            'mentioned_user_ids' => $comment->mentioned_user_ids,
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}
