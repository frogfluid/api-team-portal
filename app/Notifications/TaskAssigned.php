<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use App\Models\User;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public $task;
    public $assigner;
    public $messageStr;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, User $assigner, $messageStr = null)
    {
        $this->task = $task;
        $this->assigner = $assigner;
        $this->messageStr = $messageStr;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // we will use the database channel for the web UI
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'assigner_id' => $this->assigner->id,
            'assigner_name' => $this->assigner->name,
            'message' => $this->messageStr ?? "{$this->assigner->name} assigned you a new task: {$this->task->title}",
            'link' => route('app.tasks.show', $this->task->id),
        ];
    }
}
