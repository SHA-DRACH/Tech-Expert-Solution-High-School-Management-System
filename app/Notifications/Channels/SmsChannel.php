<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsGateway;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Delivers notifications by SMS (spec section 33).
 *
 * A notification opts in by listing 'sms' in via() and providing a toSms()
 * method returning the message text. Nothing is sent unless SMS is switched on
 * in config, so connecting a provider is a deliberate act.
 */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('sms.enabled')) {
            return;
        }

        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $number = $this->numberFor($notifiable);

        if ($number === null) {
            return;
        }

        $message = trim((string) $notification->toSms($notifiable));

        if ($message === '') {
            return;
        }

        try {
            $this->gateway->send($number, $message);
        } catch (\Throwable $e) {
            // A failed text must never take down the action that triggered it:
            // an attendance register still saves if the SMS provider is down.
            Log::error('SMS delivery failed', [
                'to' => $number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The number to text, in international format.
     *
     * Numbers are stored as people type them, so a local "077..." is expanded
     * using the configured country code rather than being sent as-is.
     */
    protected function numberFor(object $notifiable): ?string
    {
        $raw = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms()
            : ($notifiable->phone ?? null);

        if (blank($raw)) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', (string) $raw);

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        $country = config('sms.default_country_code');

        // A leading zero is the local trunk prefix and is dropped.
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, $country)) {
            return '+'.$digits;
        }

        return '+'.$country.$digits;
    }
}
