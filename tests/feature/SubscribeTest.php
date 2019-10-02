<?php

namespace OnlineVerkaufen\Plan\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Plan\Events\NewSubscription;
use OnlineVerkaufen\Plan\Events\RenewedSubscription;
use OnlineVerkaufen\Plan\Events\SubscriptionPaymentSucceeded;
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
        $this->assertNull($this->user->activeSubscription());
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

        $subscription->markAsPaid();
        $this->assertTrue($subscription->fresh()->isPaid());
        $this->assertTrue($subscription->fresh()->isActive());
    }

    /** @test * */
    public function can_subscribe_to_a_non_recurring_monthly_plan_with_test_period(): void
    {
        $plan = factory(Plan::class)->states(['monthly', 'active'])->create();
        $this->user->subscribeTo($plan, false,false,30);
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
}
