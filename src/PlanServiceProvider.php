<?php

namespace OnlineVerkaufen\Subscriptions;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;
use OnlineVerkaufen\Subscriptions\Console\Commands\RenewExpiringSubscriptionsCommand;

class PlanServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/subscriptions.php' => config_path('subscriptions.php'),
        ], 'config');

        $this->app->make(Factory::class)->load(__DIR__ . '/../database/factories');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RenewExpiringSubscriptionsCommand::class
            ]);
        }
    }
}
