<?php

namespace App\Services\Sms;

/**
 * An SMS gateway (spec section 33).
 *
 * Deliberately an interface with a log-only default: choosing and contracting
 * a Liberian SMS provider is the school's decision, and their credentials
 * cannot be guessed here. Writing the driver for a real provider means
 * implementing this one method and naming it in config/sms.php — nothing that
 * calls it has to change.
 */
interface SmsGateway
{
    /**
     * Send one message.
     *
     * @param  string  $to  the recipient's number in international format
     * @return bool whether the gateway accepted it
     */
    public function send(string $to, string $message): bool;
}
