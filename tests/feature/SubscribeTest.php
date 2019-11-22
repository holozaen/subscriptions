<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\NewSubscription;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;
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

    /** @test *
     * @throws SubscriptionException
     */
    public function can_subscribe_to_a_recurring_yearly_plan(): void
    {
        Event::fake();
        $plan = factory(Plan::class)->states(['active', 'yearly'])->create();
        $this->user->subscribeTo($plan);
        $this->assertCount(1, $this->user->subscriptions);
        /** @var Subscription $subscription */
        $subscription = $this->user->active_or_last_subscription;
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->starts_at, 1);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEqualsWithDelta(Carbon::now()->addYear()->endOfDay(), $subscription->expires_at, 1);
        $this->assertTrue($plan->is($subscription->plan));
        $this->assertEquals($plan->price, $subscription->price);
        $this->assertEquals($plan->currency, $subscription->currency);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals(Carbon::now()->addYear()->diffInDays(Carbon::now()), $subscription->remaining_days);

        $this->assertTrue($subscription->is_recurring);
        $this->assertNull($subscription->refunded_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertFalse($subscription->is_testing);
        $this->assertFalse($subscription->is_paid);
        $this->assertFalse($subscription->is_active);
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(NewSubscription::class);

        $subscription->markAsPaid();
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(SubscriptionPaymentSucceeded::class);
        $this->assertTrue($subscription->fresh()->is_paid);
        $this->assertTrue($subscription->fresh()->is_active);
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_subscribe_to_a_non_recurring_monthly_plan_with_test_period(): void
    {
        $plan = factory(Plan::class)->states(['monthly', 'active'])->create();
        Event::fake();

        $this->user->subscribeTo($plan, false,30);

        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(NewSubscription::class);
        $this->assertCount(1, $this->user->subscriptions);
        /** @var Subscription $subscription */
        $subscription = $this->user->active_subscription;
        $this->assertTrue($plan->is($subscription->plan));
        $this->assertEqualsWithDelta(Carbon::now()->addDays(30), $subscription->starts_at, 1);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEqualsWithDelta(Carbon::now()->addDays(30)->addMonth()->endOfDay(), $subscription->expires_at, 1);
        $this->assertFalse($subscription->is_recurring);
        $this->assertNull($subscription->refunded_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertTrue($subscription->is_testing);
        $this->assertNull($subscription->paid_at);
        $this->assertTrue($subscription->is_active);
        $this->assertFalse($subscription->is_paid);
        $this->assertFalse($subscription->is_pending_cancellation);
        $this->assertFalse($subscription->is_refunded);
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_subscribe_to_a_recurring_plan_with_set_duration(): void
    {
        $plan = factory(Plan::class)->states(['duration', 'active'])->create();
        Event::fake();

        $subscription = $this->user->subscribeTo($plan, true,0, 10);
        $subscription->markAsPaid();

        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(NewSubscription::class);
        $this->assertCount(1, $this->user->subscriptions);
        /** @var Subscription $subscription */
        $subscription = $this->user->active_subscription;
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->starts_at, 1);
        $this->assertTrue($plan->is($subscription->plan));
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEqualsWithDelta(Carbon::now()->addDays(10)->endOfDay(), $subscription->expires_at, 1);
        $this->assertTrue($subscription->is_recurring);
        $this->assertNull($subscription->refunded_at);
        $this->assertNull($subscription->cancelled_at);
        $this->assertFalse($subscription->is_testing);
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->paid_at, 1);
        $this->assertTrue($subscription->is_active);
        $this->assertTrue($subscription->is_paid);
        $this->assertFalse($subscription->is_pending_cancellation);
        $this->assertFalse($subscription->is_refunded);
    }

    /** @test * */
    public function can_not_subscribe_to_a_plan_with_zero_day_duration(): void
    {
        $plan = factory(Plan::class)->states(['duration', 'active'])->create();
        Event::fake();
        try {
            $this->user->subscribeTo($plan, true,0, 0);
        } catch (SubscriptionException $e) {
            $this->assertCount(0, $this->user->subscriptions);
            $this->assertCount(0, Subscription::all());
            /** @noinspection PhpUndefinedMethodInspection */
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

            $this->user->subscribeTo($plan, true, 0,10,Carbon::parse('+5 days')->toDateString());
        } catch (SubscriptionException $e) {
            $this->assertCount(1, $this->user->subscriptions);
            /** @var Subscription $subscription */
            $this->assertTrue($this->user->active_subscription->is($subscription));
            /** @noinspection PhpUndefinedMethodInspection */
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

            $this->user->subscribeTo($plan, true, 0,10,Carbon::parse('+5 days')->toDateString());
        } catch (SubscriptionException $e) {
            $this->assertCount(1, $this->user->subscriptions);
            /** @noinspection PhpUndefinedVariableInspection */
            $this->assertTrue($this->user->upcoming_subscription->is($subscription));
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(NewSubscription::class);
            return;
        }
        $this->fail('SubscriptionException expected');
    }
}
