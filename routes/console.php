<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
| Driven by a single cron entry on the server:
|
|   * * * * * cd /path/to/gsms && php artisan schedule:run >> /dev/null 2>&1
*/

// Fee reminders go out on a weekday morning, never at the weekend when nobody
// is in the office to answer the questions they prompt.
Schedule::command('gsms:fee-reminders')
    ->weekdays()
    ->at('07:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// A nightly backup, taken while the school is closed. Two weeks of history is
// kept; adjust with --keep if the disk allows more.
Schedule::command('gsms:backup')
    ->dailyAt('01:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
