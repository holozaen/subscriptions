<?php

namespace OnlineVerkaufen\Plan;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;

class PlanServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/plan.php' => config_path('plan.php'),
        ], 'config');

        $this->app->make(Factory::class)->load(__DIR__ . '/../database/factories');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/');
    }
}
