<?php

namespace OnlineVerkaufen\Plan\Test;

use OnlineVerkaufen\Plan\PlanServiceProvider;
use OnlineVerkaufen\Plan\Test\Models\User as TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;


use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    //use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'sqlite']);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->withFactories(__DIR__.'/../database/factories');

        $this->artisan('migrate', ['--database' => 'sqlite']);
    }

    protected function getPackageProviders($app)
    {
        return [
            PlanServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('app.key', 'jklafsdhigbmbfk895hkjhgfkmnbg');
        $app['config']->set('plan.models.plan', Plan::class);
        $app['config']->set('plan.models.feature', Feature::class);
        $app['config']->set('plan.models.subscription', Subscription::class);
        $app['config']->set('plan.models.usage', Feature\Usage::class);
        $app['config']->set('plan.paymentToleranceDays', 0);
    }
}
