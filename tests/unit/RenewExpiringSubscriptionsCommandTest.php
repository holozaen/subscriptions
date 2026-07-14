<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class RenewExpiringSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calls_the_renew_expiring_subscription_command_for_each_expiring_subscription(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        Subscription::factory()
            ->expiring()->recurring()
            ->create([
                'model_type' => User::class,
                'model_id' => $userA->id,
            ]);
        Subscription::factory()
            ->active()
            ->create([
                'model_type' => User::class,
                'model_id' => $userB->id
            ]);
        Subscription::factory()
            ->expiring()->recurring()
            ->create([
                'model_type' => User::class,
                'model_id' => $userC->id
            ]);

        $this->artisan('subscriptions:renew')
            ->expectsOutput('.')
            ->expectsOutput('.')
            ->assertExitCode(0);
    }
}
