<?php

namespace App\Notifications;

use App\Models\Semester;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Tells teachers they can now enter a semester's examination marks. */
class ExamEntryOpened extends Notification
{
    use Queueable;

    public function __construct(protected Semester $semester) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->semester->name.' exam marks are open',
            'body' => 'You can now enter '.strtolower($this->semester->name).' examination marks on the grade sheet.',
            'url' => route('gradesheet.index', ['sheet' => 'exam:'.$this->semester->id]),
            'icon' => '◈',
            'category' => 'Examinations & grades',
        ];
    }
}
