<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionCancelled;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionMigrated;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CancelSubscriptionTest extends TestCase
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
    public function can_cancel_a_subscription_immediately(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, false);
        $subscription->markAsPaid();
        $this->assertTrue($subscription->is_active);

        Event::fake();
        $this->user->cancelSubscription(true);
        $this->assertFalse($subscription->fresh()->is_active);
        $this->assertTrue($subscription->fresh()->is_cancelled);
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(SubscriptionCancelled::class);
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_cancel_a_subscription_on_expiration_date(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, false);
        $subscription->markAsPaid();
        $this->assertTrue($subscription->is_active);

        Event::fake();
        $this->user->cancelSubscription();
        $this->assertTrue($subscription->fresh()->is_active);
        $this->assertFalse($subscription->fresh()->is_cancelled);
        $this->assertTrue($subscription->fresh()->is_pending_cancellation);
        $this->assertEquals($subscription->fresh()->expires_at, $subscription->fresh()->cancelled_at);
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(SubscriptionCancelled::class);
    }

    /** @test * */
    public function can_only_cancel_active_subscriptions(): void
    {
        $subscription = factory(Subscription::class)->states('expired')->create([
            'model_type' => User::class,
            'model_id' => $this->user->id
        ]);
        Event::fake();
        try{
            $this->user->cancelSubscription(true);
        } catch (SubscriptionException $e) {
            $this->assertFalse($subscription->fresh()->is_cancelled);
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(SubscriptionCancelled::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_migrate_a_yearly_plan_to_a_non_recurring_plan_with_set_duration(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->active_subscription;
        $this->assertEquals('yearly', $activeSubscription->plan->type);
        Event::fake();

        $durationPlan = factory(Plan::class)->states('active', 'duration')->create();
        /** @noinspection ArgumentEqualsDefaultValueInspection */
        $newSubscription = $this->user->migrateSubscriptionTo($durationPlan, false, true, 30);
        $newSubscription->markAsPaid();

        $activeSubscription = $this->user->active_subscription;
        $this->assertTrue($activeSubscription->is($newSubscription));
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(SubscriptionMigrated::class);
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_not_migrate_a_yearly_plan_to_a_non_recurring_plan_with_zero_duration(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->active_subscription;
        $this->assertEquals('yearly', $activeSubscription->plan->type);
        $durationPlan = factory(Plan::class)->states('active', 'duration')->create();
        Event::fake();

        try{
            $this->user->migrateSubscriptionTo($durationPlan, false, true, 0);
        } catch (SubscriptionException $e) {
            $this->assertTrue($activeSubscription->is($oldSubscription));
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(SubscriptionMigrated::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function cannot_migrate_a_testing_subscription_on_the_expiry_date(): void
    {
        $this->user->subscribeTo($this->plan, false, 30);
        $activeSubscription = $this->user->active_subscription;
        $this->assertEquals('yearly', $activeSubscription->plan->type);
        Event::fake();

        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();
        try {
            /** @noinspection ArgumentEqualsDefaultValueInspection */
            $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
            $newSubscription->markAsPaid();

        } catch (SubscriptionException $e) {
            $activeSubscription = $this->user->active_subscription;
            $this->assertTrue($activeSubscription->is($activeSubscription));
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(SubscriptionMigrated::class);
            return;
        }

        $this->fail();
    }

    /** @test * */
    public function can_only_migrate_active_subscriptions(): void
    {
        factory(Subscription::class)->states('expired');
        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();
        Event::fake();

        try {
            /** @noinspection ArgumentEqualsDefaultValueInspection */
            $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
        } catch (SubscriptionException $e) {
            $this->assertFalse($this->user->hasActiveSubscription());
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(SubscriptionMigrated::class);
            return;
        }

        $this->fail();
    }
}
