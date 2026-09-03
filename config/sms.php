<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    | Which SMS gateway to use. 'log' writes messages to the log rather than
    | sending them, which is the default until a school connects a provider.
    |
    | To add a real provider: write a class implementing
    | App\Services\Sms\SmsGateway, register it below, and set SMS_GATEWAY.
    */

    'gateway' => env('SMS_GATEWAY', 'log'),

    'gateways' => [
        'log' => App\Services\Sms\LogSmsGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    | Messages are only actually sent when this is enabled, so a school can
    | connect a provider and verify the numbers before anything reaches a
    | parent's phone.
    */

    'enabled' => env('SMS_ENABLED', false),

    'log_channel' => env('SMS_LOG_CHANNEL', 'stack'),

    /*
    | Numbers are stored as people type them. This is the country code used to
    | turn a local number such as 077 000 0000 into +231 77 000 0000.
    */

    'default_country_code' => env('SMS_COUNTRY_CODE', '231'),

];
