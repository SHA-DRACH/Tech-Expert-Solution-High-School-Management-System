<?php

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Services\Assistant\AssistantProvider;
use App\Services\Assistant\LocalAssistant;
use App\Services\Sms\SmsGateway;
use App\Support\SchoolContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Must be a singleton: the tenant resolved by middleware has to be the
        // same object every global scope, policy and service reads from.
        $this->app->singleton(SchoolContext::class);

        // Whichever gateway config names. The default writes to the log
        // rather than sending, until a school connects a real provider.
        $this->app->bind(SmsGateway::class, function () {
            $gateway = config('sms.gateway', 'log');

            return $this->app->make(config("sms.gateways.{$gateway}"));
        });

        // The same seam for the assistant. The default runs on this server and
        // sends nothing anywhere; a hosted model is something a school opts in
        // to, because the data it would carry is children's marks and family
        // debts.
        $this->app->bind(AssistantProvider::class, function () {
            $provider = config('assistant.provider', 'local');

            return $this->app->make(config("assistant.providers.{$provider}", LocalAssistant::class));
        });
    }

    public function boot(): void
    {
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Catch lazy-loaded relations in development before they become N+1
        // queries on a school with thousands of students.
        Model::preventLazyLoading($this->app->isLocal());
    }
}
