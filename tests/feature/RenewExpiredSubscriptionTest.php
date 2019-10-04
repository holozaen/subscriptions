<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\NewSubscription;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionRenewed;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionExtended;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionMigrated;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RenewExpiredSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @var User $user */
    private $user;

    /** @var Subscription */
    private $subscription;

    /** @var Plan */
    private $plan;


    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->plan = factory(Plan::class)->states(['active', 'yearly'])->create();
    }

    /** @test * */
    public function can_renew_the_last_expired_subscription(): void
    {
        factory(Subscription::class)->states('expired')->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
        ]);
        Event::fake();
        $this->user->renewExpiredSubscription(true);

        $subscription = $this->user->activeSubscription();
        $this->assertEquals('yearly', $subscription->plan->type);
        $this->assertEqualsWithDelta(Carbon::now()->addYear()->endOfDay(), $subscription->expires_at, 1);
        Event::assertDispatched(SubscriptionRenewed::class);
        Event::assertNotDispatched(NewSubscription::class);
    }

    /** @test * */
    public function can_not_renew_active_subscriptions(): void
    {
        $expiredSubscription = factory(Subscription::class)->states('active')->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
        ]);

        Event::fake();

        try {
            $this->user->renewExpiredSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertTrue($this->user->activeOrLastSubscription()->is($expiredSubscription));
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail();
    }

    /** @test * */
    public function can_not_renew_without_subscriptions(): void
    {
        Event::fake();

        try {
            $this->user->renewExpiredSubscription(true);
        } catch (SubscriptionException $e) {
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail();
    }
}