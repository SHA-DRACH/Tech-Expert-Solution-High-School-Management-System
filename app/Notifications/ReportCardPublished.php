<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\ReportCard;

class ReportCardPublished extends Notification
{
    use Queueable;

    public function __construct(protected ReportCard $card) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Report card available',
            'body' => ($this->card->student?->full_name ?? 'A student').' — '.($this->card->term?->name ?? 'this period'),
            'url' => route('reportcards.show', $this->card),
            'icon' => '🗎',
            'category' => 'Report cards',
        ];
    }
}
