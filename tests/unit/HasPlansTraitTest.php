<?php

namespace OnlineVerkaufen\Plan\Test\unit;


use Carbon\Carbon;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\Models\User;
use OnlineVerkaufen\Plan\Test\TestCase;

class HasPlansTraitTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function active_or_last_subscription_returns_active_subscription_if_exists(): void
    {
        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->states('active')->create();
        /** @var User $user */
        $user = $subscription->model;
        $this->assertTrue($user->activeOrLastSubscription()->is($subscription));
    }

    /** @test */
    public function active_or_last_subscription_returns_last_past_subscription_if_no_active_subscription_exists(): void
    {
        /** @var Subscription $subscription */
        $latestExpiredSubscription = factory(Subscription::class)->states('expired')->create(['expires_at' => Carbon::parse('-1 weeks')]);
        factory(Subscription::class)->states('expired')->create(['expires_at' => Carbon::parse('-2 weeks')]);
        /** @var User $user */
        $user = $latestExpiredSubscription->model;
        $this->assertTrue($user->activeOrLastSubscription()->is($latestExpiredSubscription));
    }

    /** @test */
    public function it_knows_whether_it_has_an_active_subscription(): void
    {
        /** @var Subscription $subscription */
        $expiredSubscription = factory(Subscription::class)->states('expired')->create();
        /** @var User $user */
        $user = $expiredSubscription->model;
        $this->assertFalse($user->hasActiveSubscription());
        factory(Subscription::class)->states('active')->create([
            'model_type' => User::class,
            'model_id' => $user->id
        ]);
        $this->assertTrue($user->hasActiveSubscription());
    }

    /** @test */
    public function it_knows_whether_it_has_unpaid_subscriptions(): void
    {
        /** @var Subscription $subscription */
        $paidSubscription = factory(Subscription::class)->states('active')->create();
        /** @var User $user */
        $user = $paidSubscription->model;
        $this->assertFalse($user->hasUnpaidSubscriptions());
        $unpaidSubscription= factory(Subscription::class)->states('unpaid')->create([
            'model_type' => User::class,
            'model_id' => $user->id
        ]);
        $this->assertTrue($user->hasUnpaidSubscriptions());
    }

    /** @test */
    public function it_knows_whether_it_has_upcoming_subscriptions_including_current_testing(): void
    {
        /** @var Subscription $subscription */
        $activeSubscription = factory(Subscription::class)->states('active')->create();
        /** @var User $user */
        $user = $activeSubscription->model;
        $this->assertFalse($user->hasUpcomingSubscription());
        $testingSubscription = factory(Subscription::class)->states('testing')->create([
            'model_type' => User::class,
            'model_id' => $user->id
        ]);
        $this->assertTrue($user->hasUpcomingSubscription());
    }

}
