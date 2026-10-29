<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class FinanceNotification extends Notification
{
    public function __construct(
        private readonly string $eventKey,
        private readonly string $title,
        private readonly string $message,
        private readonly array $details = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_key' => $this->eventKey,
            'title' => $this->title,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
