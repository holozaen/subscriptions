<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionExtended;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionMigrated;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;
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

        $this->user = User::factory()->create();
        $this->plan = Plan::factory()->active()->yearly()->create();
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_extend_an_existing_subscription(): void
    {
        $activeSubscription = Subscription::factory()->active()->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::parse('+ 1 weeks')
        ]);
        Event::fake();
        $this->user->extendSubscription(10);

        $subscription = $this->user->active_subscription;
        $this->assertTrue($subscription->is($activeSubscription));
        /** @noinspection PhpUndefinedMethodInspection */
        /** @noinspection ArgumentEqualsDefaultValueInspection */
        $this->assertEqualsWithDelta(Carbon::now()->addWeeks(1)->addDays(10)->endOfDay(), $subscription->expires_at, 1);
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(SubscriptionExtended::class);
    }

    /** @test * */
    public function can_only_extend_active_subscriptions(): void
    {
        Subscription::factory()->expired()->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::yesterday()
        ]);

        Event::fake();

        try {
            $this->user->extendSubscription(10);
        } catch (SubscriptionException $e) {
            $this->assertEqualsWithDelta(Carbon::yesterday(), $this->user->active_or_last_subscription->expires_at, 1);
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(SubscriptionExtended::class);
            return;
        }

        $this->fail();
    }

    /** @test *
     * @throws SubscriptionException
     */
    public function can_extend_an_existing_subscription_to_a_certain_date(): void
    {
        $activeSubscription = Subscription::factory()->active()->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::parse('+ 1 weeks')
        ]);
        Event::fake();
        $this->user->extendSubscriptionTo(Carbon::parse('+ 2 weeks'));

        $subscription = $this->user->active_subscription;
        $this->assertTrue($subscription->is($activeSubscription));
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEqualsWithDelta(Carbon::now()->addWeeks(2)->endOfDay(), $subscription->expires_at, 1);
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(SubscriptionExtended::class);
    }

    /** @test * */
    public function can_only_extend_active_subscriptions_to_a_certain_date(): void
    {
        Subscription::factory()->expired()->create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'expires_at' => Carbon::yesterday()
        ]);

        Event::fake();

        try {
            $this->user->extendSubscriptionTo(Carbon::parse('+ 2 weeks'));
        } catch (SubscriptionException $e) {
            $this->assertEqualsWithDelta(Carbon::yesterday(), $this->user->active_or_last_subscription->expires_at, 1);
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(SubscriptionExtended::class);
            return;
        }

        $this->fail();
    }


    /** @test *
     * @throws SubscriptionException
     */
    public function can_migrate_a_yearly_plan_to_a_monthly_plan_on_the_expiry_date(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->active_subscription;
        $this->assertEquals('yearly', $activeSubscription->plan->type);
        sleep(1);

        $monthlyPlan = Plan::factory()->active()->monthly()->create();
        /** @noinspection ArgumentEqualsDefaultValueInspection */
        $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
        $newSubscription->markAsPaid();

        $activeSubscription = $this->user->active_subscription;
        $this->assertTrue($activeSubscription->is($oldSubscription));
        $latestSubscription = $this->user->latest_subscription;
        $this->assertEquals('monthly', $latestSubscription->plan->type);
        $this->assertEqualsWithDelta($activeSubscription->expires_at, $latestSubscription->starts_at, 1);
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

        $durationPlan = Plan::factory()->active()->duration()->create();
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
        $durationPlan = Plan::factory()->active()->duration()->create();
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

        $monthlyPlan = Plan::factory()->active()->monthly()->create();
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
}
