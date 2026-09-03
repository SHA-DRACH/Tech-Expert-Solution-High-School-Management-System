<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

use App\Models\Invoice;
use App\Support\Money;

class FeeReminder extends Notification
{
    use Queueable;

    public function __construct(protected Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Fees outstanding',
            'body' => Money::format($this->invoice->balanceMinor()).' remains on invoice '.
                $this->invoice->invoice_number.
                ($this->invoice->due_on ? ', due '.$this->invoice->due_on->format('j M Y').'.' : '.'),
            'url' => route('parent.fees', ['child' => $this->invoice->student_id]),
            'icon' => '◫',
            'category' => 'Fees',
        ];
    }
}
