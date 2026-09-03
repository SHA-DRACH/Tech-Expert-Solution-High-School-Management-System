<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\Assessment;

class GradesAwaitingApproval extends Notification
{
    use Queueable;

    public function __construct(protected Assessment $assessment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Marks submitted for approval',
            'body' => $this->assessment->title.' — '.($this->assessment->section?->full_name ?? 'a class'),
            'url' => route('grades.review', $this->assessment),
            'icon' => '◈',
            'category' => 'Examinations & grades',
        ];
    }
}
