<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\Admission;

class AdmissionSubmitted extends Notification
{
    use Queueable;

    public function __construct(protected Admission $admission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New admission application',
            'body' => $this->admission->student_name.' applied for '.($this->admission->intended_class ?? 'admission').'.',
            'url' => route('admissions.show', $this->admission),
            'icon' => '✦',
            'category' => 'Admissions',
        ];
    }
}
