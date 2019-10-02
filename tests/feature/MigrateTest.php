<?php

namespace OnlineVerkaufen\Plan\Test\feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Plan\Events\NewSubscription;
use OnlineVerkaufen\Plan\Events\RenewedSubscription;
use OnlineVerkaufen\Plan\Events\SubscriptionMigrated;
use OnlineVerkaufen\Plan\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\Models\User;
use OnlineVerkaufen\Plan\Test\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MigrateTest extends TestCase
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
    public function can_migrate_a_yearly_plan_in_test_phase_to_a_monthly_plan_immediately(): void
    {
        $this->user->subscribeTo($this->plan, false, false, 30);
        $oldsubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $oldsubscription->plan->type);
        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();

        Event::fake();
        $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, true);
        $newSubscription->markAsPaid();

        $subscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_MONTHLY, $subscription->plan->type);
        $this->assertEqualsWithDelta(Carbon::now(), $subscription->starts_at, 1);
        Event::assertNotDispatched(NewSubscription::class);
        Event::assertDispatched(SubscriptionMigrated::class);
    }

    /** @test * */
    public function can_migrate_a_yearly_plan_to_a_monthly_plan_on_the_expiry_date(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false, false, 0);
        $oldSubscription->markAsPaid();
        $activeSubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $activeSubscription->plan->type);
        sleep(1);

        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();
        $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
        $newSubscription->markAsPaid();

        $activeSubscription = $this->user->activeSubscription();
        $this->assertTrue($activeSubscription->is($activeSubscription));
        $latestSubscription = $this->user->latestSubscription();
        $this->assertEquals(Plan::TYPE_MONTHLY, $latestSubscription->plan->type);
        $this->assertEqualsWithDelta($activeSubscription->expires_at, $latestSubscription->starts_at, 1);
    }

    /** @test * */
    public function cannot_migrate_a_testing_subscription_on_the_expiry_date(): void
    {
        $oldSubscription = $this->user->subscribeTo($this->plan, false, false, 30);
        $activeSubscription = $this->user->activeSubscription();
        $this->assertEquals(Plan::TYPE_YEARLY, $activeSubscription->plan->type);

        $monthlyPlan = factory(Plan::class)->states('active', 'monthly')->create();
        try {
            $newSubscription = $this->user->migrateSubscriptionTo($monthlyPlan, true, false);
            $newSubscription->markAsPaid();

        } catch (SubscriptionException $e) {
            $activeSubscription = $this->user->activeSubscription();
            $this->assertTrue($activeSubscription->is($activeSubscription));
            return;
        }

        $this->fail();
    }

}
