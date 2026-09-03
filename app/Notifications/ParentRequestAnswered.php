<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\ParentRequest;

class ParentRequestAnswered extends Notification
{
    use Queueable;

    public function __construct(protected ParentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'The school replied to your request',
            'body' => $this->request->subject,
            'url' => route('parent.requests'),
            'icon' => '✉',
            'category' => 'Parent requests',
        ];
    }
}
