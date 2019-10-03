<?php

namespace OnlineVerkaufen\Plan\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Plan\Events\NewSubscription;
use OnlineVerkaufen\Plan\Events\SubscriptionRenewed;
use OnlineVerkaufen\Plan\Events\SubscriptionExtended;
use OnlineVerkaufen\Plan\Events\SubscriptionMigrated;
use OnlineVerkaufen\Plan\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\Models\User;
use OnlineVerkaufen\Plan\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExtendSubscriptionTest extends TestCase
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
    public function can_extend_an_existing_subscription(): void
    {
        $activeSubscription = factory(Subscription::class)->states('active')->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::parse('+ 1 weeks')
        ]);
        Event::fake();
        $this->user->extendSubscription(10);

        $subscription = $this->user->activeSubscription();
        $this->assertTrue($subscription->is($activeSubscription));
        $this->assertEqualsWithDelta(Carbon::now()->addWeeks(1)->addDays(10)->endOfDay(), $subscription->expires_at, 1);
        Event::assertDispatched(SubscriptionExtended::class);
    }

    /** @test * */
    public function can_only_extend_active_subscriptions(): void
    {
        $expiredSubscription = factory(Subscription::class)->states('expired')->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::yesterday()
        ]);

        Event::fake();

        try {
            $this->user->extendSubscription(10);
        } catch (SubscriptionException $e) {
            $this->assertEqualsWithDelta(Carbon::yesterday(), $this->user->activeOrLastSubscription()->expires_at, 1);
            Event::assertNotDispatched(SubscriptionExtended::class);
            return;
        }

        $this->fail();
    }

    /** @test * */
    public function can_extend_an_existing_subscription_to_a_certain_date(): void
    {
        $activeSubscription = factory(Subscription::class)->states('active')->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::parse('+ 1 weeks')
        ]);
        Event::fake();
        $this->user->extendSubscriptionTo(Carbon::parse('+ 2 weeks'));

        $subscription = $this->user->activeSubscription();
        $this->assertTrue($subscription->is($activeSubscription));
        $this->assertEqualsWithDelta(Carbon::now()->addWeeks(2)->endOfDay(), $subscription->expires_at, 1);
        Event::assertDispatched(SubscriptionExtended::class);
    }

    /** @test * */
    public function can_only_extend_active_subscriptions_to_a_certain_date(): void
    {
        $expiredSubscription = factory(Subscription::class)->states('expired')->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::yesterday()
        ]);

        Event::fake();

        try {
            $this->user->extendSubscriptionTo(Carbon::parse('+ 2 weeks'));
        } catch (SubscriptionException $e) {
            $this->assertEqualsWithDelta(Carbon::yesterday(), $this->user->activeOrLastSubscription()->expires_at, 1);
            Event::assertNotDispatched(SubscriptionExtended::class);
            return;
        }

        $this->fail();
    }



    /** @test * */
    public function can_migrate_a_yearly_plan_to_a_monthly_plan_on_the_expiry_date(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false, 0);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $activeSubscription->plan->type);
        sleep(1);

        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();
        $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
        $newSubscription->markAsPaid();

        $activeSubscription = $this->user->activeSubscription();
        $this->assertTrue($activeSubscription->is($oldSubscription));
        $latestSubscription = $this->user->latestSubscription();
        $this->assertEquals(Plan::TYPE_MONTHLY, $latestSubscription->plan->type);
        $this->assertEqualsWithDelta($activeSubscription->expires_at, $latestSubscription->starts_at, 1);
    }

    /** @test * */
    public function can_migrate_a_yearly_plan_to_a_non_recurring_plan_with_set_duration(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false, 0);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $activeSubscription->plan->type);
        Event::fake();

        $durationPlan = factory(Plan::class)->states('active', 'duration')->create();
        $newSubscription = $this->user->migrateSubscriptionTo($durationPlan, false, true, 30);
        $newSubscription->markAsPaid();

        $activeSubscription = $this->user->activeSubscription();
        $this->assertTrue($activeSubscription->is($newSubscription));
        Event::assertDispatched(SubscriptionMigrated::class);
    }

    /** @test * */
    public function can_not_migrate_a_yearly_plan_to_a_non_recurring_plan_with_zero_duration(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false, 0);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $activeSubscription->plan->type);
        $durationPlan = factory(Plan::class)->states('active', 'duration')->create();
        Event::fake();

        try{
            $newSubscription = $this->user->migrateSubscriptionTo($durationPlan, false, true, 0);
        } catch (SubscriptionException $e) {
            $this->assertTrue($activeSubscription->is($oldSubscription));
            Event::assertNotDispatched(SubscriptionMigrated::class);
            return;
        }

        $this->fail('Expected SubscriptionException');
    }

    /** @test * */
    public function cannot_migrate_a_testing_subscription_on_the_expiry_date(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false, 30);
        $activeSubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $activeSubscription->plan->type);
        Event::fake();

        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();
        try {
            $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
            $newSubscription->markAsPaid();

        } catch (SubscriptionException $e) {
            $activeSubscription = $this->user->activeSubscription();
            $this->assertTrue($activeSubscription->is($activeSubscription));
            Event::assertNotDispatched(SubscriptionMigrated::class);
            return;
        }

        $this->fail();
    }
}
