<?php

namespace OnlineVerkaufen\Plan\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Plan\Events\NewSubscription;
use OnlineVerkaufen\Plan\Events\SubscriptionRenewed;
use OnlineVerkaufen\Plan\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\Models\User;
use OnlineVerkaufen\Plan\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscribeTest extends TestCase
{
    use RefreshDatabase;

    /** @var User $user */
    private $user;


    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    /** @test * */
    public function can_subscribe_to_a_recurring_yearly_plan(): void
    {
        Event::fake();
        $plan = factory(Plan::class)->states(['active', 'yearly'])->create();
        $this->user->subscribeTo($plan);
        Event::assertDispatched(NewSubscription::class);
        $this->assertCount(1, $this->user->subscriptions);
        /** @var Subscription $subscription */
        $subscription = $this->user->activeOrLastSubscription();
        $this->assertTrue($plan->is($subscription->plan));
        $this->assertEquals($plan->price, $subscription->price);
        $this->assertEquals($plan->currency, $subscription->currency);
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->starts_at, 1);
        $this->assertEqualsWithDelta(Carbon::now()->addYear()->endOfDay(), $subscription->expires_at, 1);
        $this->assertEquals(Carbon::now()->addYear()->diffInDays(Carbon::now()), $subscription->remaining_days);

        $this->assertTrue($subscription->is_recurring);
        $this->assertNull($subscription->refunded_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertFalse($subscription->isTesting());
        $this->assertFalse($subscription->isPaid());
        $this->assertFalse($subscription->isActive());

        Event::fake();
        $subscription->markAsPaid();
        Event::assertDispatched(SubscriptionPaymentSucceeded::class);
        $this->assertTrue($subscription->fresh()->isPaid());
        $this->assertTrue($subscription->fresh()->isActive());
    }

    /** @test * */
    public function can_subscribe_to_a_non_recurring_monthly_plan_with_test_period(): void
    {
        $plan = factory(Plan::class)->states(['monthly', 'active'])->create();
        Event::fake();

        $this->user->subscribeTo($plan, false,30);

        Event::assertDispatched(NewSubscription::class);
        $this->assertCount(1, $this->user->subscriptions);
        /** @var Subscription $subscription */
        $subscription = $this->user->activeSubscription();
        $this->assertTrue($plan->is($subscription->plan));
        $this->assertEqualsWithDelta(Carbon::now()->addDays(30), $subscription->starts_at, 1);
        $this->assertEqualsWithDelta(Carbon::now()->addDays(30)->addMonth()->endOfDay(), $subscription->expires_at, 1);
        $this->assertFalse($subscription->is_recurring);
        $this->assertNull($subscription->refunded_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertTrue($subscription->isTesting());
        $this->assertNull($subscription->paid_at);
        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isPaid());
        $this->assertFalse($subscription->isPendingCancellation());
        $this->assertFalse($subscription->isRefunded());
    }

    /** @test * */
    public function can_subscribe_to_a_recurring_plan_with_set_duration(): void
    {
        $plan = factory(Plan::class)->states(['duration', 'active'])->create();
        Event::fake();

        $subscription = $this->user->subscribeTo($plan, true,0, 10);
        $subscription->markAsPaid();

        Event::assertDispatched(NewSubscription::class);
        $this->assertCount(1, $this->user->subscriptions);
        /** @var Subscription $subscription */
        $subscription = $this->user->activeSubscription();
        $this->assertTrue($plan->is($subscription->plan));
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->starts_at, 1);
        $this->assertEqualsWithDelta(Carbon::now()->addDays(10)->endOfDay(), $subscription->expires_at, 1);
        $this->assertTrue($subscription->is_recurring);
        $this->assertNull($subscription->refunded_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertFalse($subscription->isTesting());
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->paid_at, 1);
        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->isPaid());
        $this->assertFalse($subscription->isPendingCancellation());
        $this->assertFalse($subscription->isRefunded());
    }

    /** @test * */
    public function can_not_subscribe_to_a_plan_with_zero_day_duration(): void
    {
        $plan = factory(Plan::class)->states(['duration', 'active'])->create();
        Event::fake();
        try {
            $subscription = $this->user->subscribeTo($plan, true,0, 0);
        } catch (SubscriptionException $e) {
            $this->assertCount(0, $this->user->subscriptions);
            $this->assertCount(0, Subscription::all());
            Event::assertNotDispatched(NewSubscription::class);
            return;
        }
        $this->fail('SubscriptionException expected');
    }

    /** @test * */
    public function can_not_subscribe_overlapping_active_subscription(): void
    {
        $plan = factory(Plan::class)->states(['duration', 'active'])->create();
        try {
            $subscription = $this->user->subscribeTo($plan, true,0, 10);
            $subscription->markAsPaid();
            Event::fake();

            $secondSubscription = $this->user->subscribeTo($plan, true, 0,10,Carbon::parse('+5 days')->toDateString());
        } catch (SubscriptionException $e) {
            $this->assertCount(1, $this->user->subscriptions);
            $this->assertTrue($this->user->activeSubscription()->is($subscription));
            Event::assertNotDispatched(NewSubscription::class);
            return;
        }
        $this->fail('SubscriptionException expected');
    }

    /** @test * */
    public function can_not_subscribe_overlapping_other_future_subscription(): void
    {
        $plan = factory(Plan::class)->states(['duration', 'active'])->create();
        try {
            $subscription = $this->user->subscribeTo($plan, true,0, 10, Carbon::parse('+2 days')->toDateString());
            $subscription->markAsPaid();
            Event::fake();

            $secondSubscription = $this->user->subscribeTo($plan, true, 0,10,Carbon::parse('+5 days')->toDateString());
        } catch (SubscriptionException $e) {
            $this->assertCount(1, $this->user->subscriptions);
            $this->assertTrue($this->user->upcomingSubscription()->is($subscription));
            Event::assertNotDispatched(NewSubscription::class);
            return;
        }
        $this->fail('SubscriptionException expected');
    }
}
