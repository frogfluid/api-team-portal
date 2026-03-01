<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WeeklyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WeeklyReportSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public $report;

    public $user;

    public function __construct(WeeklyReport $report, User $user)
    {
        $this->report = $report;
        $this->user = $user;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'message' => "{$this->user->name} submitted a weekly report for review.",
            'link' => route('app.reviews.index'),
        ];
    }
}
