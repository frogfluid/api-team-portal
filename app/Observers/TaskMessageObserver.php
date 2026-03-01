<?php

namespace App\Observers;

use App\Models\TaskMessage;

class TaskMessageObserver
{
    public function created(TaskMessage $message): void
    {
        $message->task?->touchActivity($message->created_at);
    }
}
