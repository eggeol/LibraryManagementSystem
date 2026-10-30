<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LibraryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public array $meta = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'meta' => $this->meta,
        ];
    }
}
