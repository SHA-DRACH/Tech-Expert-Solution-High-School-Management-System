<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\AttendanceRecord;

class AttendanceAlert extends Notification
{
    use Queueable;

    public function __construct(protected AttendanceRecord $record) {}

    public function via(object $notifiable): array
    {
        // SMS is added only when a school has connected a gateway.
        return config('sms.enabled') ? ['database', 'sms'] : ['database'];
    }

    /** The spec's example wording: "Your child was marked absent today." */
    public function toSms(object $notifiable): string
    {
        $student = $this->record->student;
        $name = $student?->first_name ?? 'Your child';

        return sprintf(
            '%s was marked %s on %s. - %s',
            $name,
            $this->record->status,
            $this->record->recorded_on->format('j M'),
            $this->record->school?->short_name ?? $this->record->school?->name ?? 'your school',
        );
    }

    public function toArray(object $notifiable): array
    {
        $student = $this->record->student;

        return [
            'title' => 'Attendance alert',
            'body' => ($student?->full_name ?? 'Your child').' was marked '.
                $this->record->status.' on '.$this->record->recorded_on->format('j M Y').'.',
            'url' => route('parent.attendance', ['child' => $this->record->student_id]),
            'icon' => '◷',
            'category' => 'Attendance',
        ];
    }
}
