<?php

namespace OnlineVerkaufen\Subscriptions\Test;

use OnlineVerkaufen\Subscriptions\SubscriptionServiceProvider;
use OnlineVerkaufen\Subscriptions\Test\Models\User as TestUser;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;


use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    //use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'sqlite']);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/fixtures/migrations');
        $this->withFactories(__DIR__.'/../database/factories');
        $this->withFactories(__DIR__.'/fixtures/factories');

        $this->artisan('migrate', ['--database' => 'sqlite']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SubscriptionServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('app.key', 'jklafsdhigbmbfk895hkjhgfkmnbg');
        $app['config']->set('subscriptions.models.plan', Plan::class);
        $app['config']->set('subscriptions.models.feature', Feature::class);
        $app['config']->set('subscriptions.models.subscription', Subscription::class);
        $app['config']->set('subscriptions.paymentToleranceDays', 0);
    }
}
