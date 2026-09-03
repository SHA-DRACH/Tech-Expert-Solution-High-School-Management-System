<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * The default gateway: writes the message to the log instead of sending it.
 *
 * This is what ships until a school connects a real provider, so the rest of
 * the system can be built and tested against a working interface without
 * anyone being charged for messages, and without a half-finished integration
 * pretending to deliver.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::channel(config('sms.log_channel', 'stack'))->info('SMS (not delivered: no gateway configured)', [
            'to' => $to,
            'message' => $message,
        ]);

        return true;
    }
}
