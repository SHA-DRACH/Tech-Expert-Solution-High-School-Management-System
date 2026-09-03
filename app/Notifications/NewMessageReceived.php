<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\MessageThread;
use App\Models\User;

class NewMessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        protected MessageThread $thread,
        protected User $sender,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New message from '.$this->sender->name,
            'body' => $this->thread->subject,
            'url' => route('messages.show', $this->thread),
            'icon' => '✉',
            'category' => 'Messages',
        ];
    }
}
