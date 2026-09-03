<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    | Which assistant answers questions. 'local' runs entirely on this server:
    | it reads the school's own database through the same permission checks as
    | the screens, and nothing a user asks it leaves the building.
    |
    | That is the default on purpose. This system holds children's marks and
    | families' debts, and sending those to a third party is a decision a school
    | should make deliberately rather than inherit.
    |
    | To connect a hosted model: write a class implementing
    | App\Services\Assistant\AssistantProvider, register it below, and set
    | ASSISTANT_PROVIDER. Whatever it sends, and where, becomes the school's
    | responsibility - say so in the school's privacy notice before enabling it.
    */

    'provider' => env('ASSISTANT_PROVIDER', 'local'),

    'providers' => [
        'local' => App\Services\Assistant\LocalAssistant::class,
    ],

    /*
    | The assistant can be switched off entirely for a deployment that does not
    | want it. The panel disappears; no route is left reachable.
    */

    'enabled' => env('ASSISTANT_ENABLED', true),

];
