<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WorkScheduleCommentMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkSchedule $schedule,
        public WorkScheduleComment $comment,
        public User $actor
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'comment_id' => $this->comment->id,
            'user_id' => $this->actor->id,
            'user_name' => $this->actor->name,
            'message' => "{$this->actor->name} mentioned you in a schedule comment.",
            'link' => route('app.schedules.calendar'),
        ];
    }
}
