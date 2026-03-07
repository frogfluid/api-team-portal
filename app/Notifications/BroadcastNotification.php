<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $message,
        private array $data = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'broadcast',
            'title' => $this->title,
            'message' => $this->message,
            'related_id' => $this->data['related_id'] ?? null,
            'meta' => $this->data,
        ];
    }
}
