<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionRenewed;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RenewExpiringSubscriptionTest extends TestCase
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

    /** @test *
     * @throws SubscriptionException
     */
    public function can_renew_a_recurring_subscription_that_expires_tomorrow(): void
    {
        $activeSubscription = factory(Subscription::class)->states(['active', 'recurring'])->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::tomorrow()->endOfDay()
        ]);

        Event::fake();
        /** @var Subscription $upcomingSubscription */
        $upcomingSubscription = $this->user->renewExpiringSubscription(true);

        $this->assertTrue($this->user->hasUpcomingSubscription());
        $this->assertTrue($this->user->active_subscription->is($activeSubscription));
        $this->assertTrue($this->user->upcoming_subscription->is($upcomingSubscription));
        $this->assertTrue($upcomingSubscription->is_paid);
        $this->assertTrue($upcomingSubscription->is_renewed);
        $this->assertEqualsWithDelta(Carbon::now(), $upcomingSubscription->renewed_at, 3);
        Event::assertDispatched(SubscriptionRenewed::class);
    }

    /** @test * */
    public function cannot_renew_a_recurring_subscription_that_expires_later_than_tomorrow(): void
    {
        $activeSubscription = factory(Subscription::class)->states(['active', 'recurring'])->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::parse('+ 5 days')->endOfDay()
        ]);

        Event::fake();
        try {
            $this->user->renewExpiringSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertFalse($this->user->hasUpcomingSubscription());
            $this->assertTrue($this->user->active_subscription->is($activeSubscription));
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test * */
    public function cannot_renew_a_recurring_subscription_that_expires_earlier_than_tomorrow(): void
    {
        $activeSubscription = factory(Subscription::class)->states(['active', 'recurring'])->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::today()->endOfDay()
        ]);

        Event::fake();
        try {
            $this->user->renewExpiringSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertFalse($this->user->hasUpcomingSubscription());
            $this->assertTrue($this->user->active_subscription->is($activeSubscription));
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test * */
    public function cannot_renew_a_non_recurring_subscription(): void
    {
        $activeSubscription = factory(Subscription::class)->states(['active', 'nonrecurring'])->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::tomorrow()->endOfDay()
        ]);

        Event::fake();
        try {
            $this->user->renewExpiringSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertFalse($this->user->hasUpcomingSubscription());
            $this->assertTrue($this->user->active_subscription->is($activeSubscription));
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test * */
    public function cannot_renew_an_unpaid_subscription(): void
    {
        $activeSubscription = factory(Subscription::class)->states(['recurring', 'tolerance'])->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::tomorrow()->endOfDay()
        ]);

        Event::fake();
        try {
            $this->user->renewExpiringSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertFalse($this->user->hasUpcomingSubscription());
            $this->assertTrue($this->user->active_subscription->is($activeSubscription));
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test * */
    public function can_not_renew_without_subscriptions(): void
    {
        Event::fake();

        try {
            $this->user->renewExpiringSubscription(true);
        } catch (SubscriptionException $e) {
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail();
    }

    /** @test * */
    public function cannot_renew_a_subscription_pending_cancellation(): void
    {
        $activeSubscription = factory(Subscription::class)->states(['active', 'recurring'])->create([
            'plan_id' => $this->plan->id,
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::tomorrow()->endOfDay(),
            'cancelled_at' => Carbon::today()->endOfDay()
        ]);

        Event::fake();
        try {
            $this->user->renewExpiringSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertFalse($this->user->hasUpcomingSubscription());
            $this->assertTrue($this->user->active_subscription->is($activeSubscription));
            Event::assertNotDispatched(SubscriptionRenewed::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

}

