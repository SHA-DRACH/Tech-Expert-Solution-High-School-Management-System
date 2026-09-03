<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\Assessment;

class GradesDecided extends Notification
{
    use Queueable;

    public function __construct(protected Assessment $assessment, protected bool $approved) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->approved ? 'Your marks were approved' : 'Marks sent back for correction',
            'body' => $this->assessment->title.($this->approved
                ? ' is now official.'
                : ': '.($this->assessment->review_note ?? 'Please review and resubmit.')),
            'url' => route('assessments.scores', $this->assessment),
            'icon' => $this->approved ? '✓' : '↩',
            'category' => 'Examinations & grades',
        ];
    }
}
