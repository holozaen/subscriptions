<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;

use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class RenewExpiringSubscriptionsCommandTest extends TestCase
{

    /** @test */
    public function it_calls_the_renew_expiring_subscription_command_for_each_expiring_subscription(): void
    {
        $userA = factory(User::class)->create();
        $userB = factory(User::class)->create();
        $userC = factory(User::class)->create();

        factory(Subscription::class)
            ->states(['expiring', 'recurring'])
            ->create([
                'model_type' => User::class,
                'model_id' => $userA->id,
            ]);
        factory(Subscription::class)
            ->states('active')
            ->create([
                'model_type' => User::class,
                'model_id' => $userB->id
            ]);
        factory(Subscription::class)
            ->states(['expiring', 'recurring'])
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
