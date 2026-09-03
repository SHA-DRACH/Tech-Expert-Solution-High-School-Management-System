<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\Announcement;

class AnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(protected Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->announcement->is_emergency
                ? 'Urgent: '.$this->announcement->title
                : $this->announcement->title,
            'body' => \Illuminate\Support\Str::limit(strip_tags($this->announcement->body), 140),
            'url' => route('notifications.index'),
            'icon' => $this->announcement->is_emergency ? '⚠' : '✦',
            'category' => 'Announcements',
        ];
    }
}
