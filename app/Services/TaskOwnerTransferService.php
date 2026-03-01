<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskOwnerHistory;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\TaskAssigned;

class TaskOwnerTransferService
{
    public function transfer(Task $task, int $toOwnerId, int $changedByUserId, ?string $note = null): Task
    {
        return DB::transaction(function () use ($task, $toOwnerId, $changedByUserId, $note) {

            if ((int) $task->owner_id === (int) $toOwnerId) {
                return $task; // no-op
            }

            TaskOwnerHistory::create([
                'task_id' => $task->id,
                'from_owner_id' => $task->owner_id,
                'to_owner_id' => $toOwnerId,
                'changed_by' => $changedByUserId,
                'note' => $note,
                'changed_at' => now(),
            ]);

            $task->forceFill([
                'owner_id' => $toOwnerId,
                'last_activity_at' => now(),
            ])->save();

            $assigner = User::find($changedByUserId);
            $assignee = User::find($toOwnerId);
            if ($assigner && $assignee && $assigner->id !== $assignee->id) {
                $assignee->notify(new TaskAssigned($task, $assigner, "{$assigner->name} transferred task '{$task->title}' to you."));
            }

            return $task->refresh();
        });
    }
}
