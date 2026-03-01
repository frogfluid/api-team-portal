<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkDailyLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DailyLogSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public $log;

    public $user;

    public function __construct(WorkDailyLog $log, User $user)
    {
        $this->log = $log;
        $this->user = $user;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'log_id' => $this->log->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'message' => "{$this->user->name} submitted a daily log for review.",
            'link' => route('app.reviews.index'),
        ];
    }
}
