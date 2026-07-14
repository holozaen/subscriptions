<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_knows_the_model_it_is_assigned_to(): void
    {
      $user = User::factory()->create();
      /** @var Subscription $subscription */
      $subscription = Subscription::factory()->create([
          'model_type' => User::class,
          'model_id' => $user->id
      ]);
      $this->assertTrue($subscription->model->is($user));
    }

    /** @test */
    public function can_get_all_active_subscriptions(): void
    {
        /** @var Subscription $activeSubscriptionA */
        $activeSubscriptionA = Subscription::factory()->active()->create();
        /** @var Subscription $activeSubscriptionB */
        $activeSubscriptionB = Subscription::factory()->testing()->create();
        /** @var Subscription $activeSubscriptionC */
        $activeSubscriptionC = Subscription::factory()->tolerance()->create();

        Subscription::factory()->unpaid()->create();
        Subscription::factory()->expired()->create();
        Subscription::factory()->cancelled()->create();
        Subscription::factory()->refunded()->create();

        $this->assertCount(3, Subscription::active()->get());
        $this->assertTrue($activeSubscriptionA->is(Subscription::active()->get()[0]));
        $this->assertTrue($activeSubscriptionB->is(Subscription::active()->get()[1]));
        $this->assertTrue($activeSubscriptionC->is(Subscription::active()->get()[2]));
        $this->assertTrue($activeSubscriptionA->is_active);
        $this->assertTrue($activeSubscriptionB->is_active);
        $this->assertTrue($activeSubscriptionC->is_active);
    }

    /** @test */
    public function can_get_paid_subscriptions(): void
    {
        /** @var Subscription $paidSubscriptionA */
        $paidSubscriptionA = Subscription::factory()->paid()->create();
        /** @var Subscription $paidSubscriptionB */
        $paidSubscriptionB = Subscription::factory()->paid()->create();
        /** @var Subscription $unpaidSubscriptionC */
        $unpaidSubscriptionC = Subscription::factory()->unpaid()->create();
        /** @var Subscription $unpaidSubscriptionD */
        $unpaidSubscriptionD = Subscription::factory()->unpaid()->create();
        $this->assertCount(2, Subscription::paid()->get());
        $this->assertCount(2, Subscription::unpaid()->get());
        $this->assertTrue($paidSubscriptionA->is(Subscription::paid()->get()[0]));
        $this->assertTrue($paidSubscriptionB->is(Subscription::paid()->get()[1]));
        $this->assertTrue($unpaidSubscriptionC->is(Subscription::unpaid()->get()[0]));
        $this->assertTrue($unpaidSubscriptionD->is(Subscription::unpaid()->get()[1]));
        $this->assertTrue($paidSubscriptionA->is_paid);
        $this->assertTrue($paidSubscriptionB->is_paid);
        $this->assertFalse($unpaidSubscriptionC->is_paid);
        $this->assertFalse($unpaidSubscriptionD->is_paid);
    }

    /** @test */
    public function can_get_subscriptions_within_payment_tolerance(): void
    {
        /** @var Subscription $subscriptionWithinPaymentToleranceA */
        $subscriptionWithinPaymentToleranceA = Subscription::factory()->tolerance()->create();
        /** @var Subscription $paidSubscriptionWithinPaymentToleranceB */
        $paidSubscriptionWithinPaymentToleranceB = Subscription::factory()->paid()->create(['payment_tolerance_ends_at' => Carbon::tomorrow()]);
        /** @var Subscription $paidSubscriptionBWithinPaymentTolerance */
        $subscriptionOutsidePaymentToleranceC = Subscription::factory()->unpaid()->create(['payment_tolerance_ends_at' => Carbon::yesterday()]);
        /** @var Subscription $paidSubscriptionBWithinPaymentTolerance */
        $subscriptionOutsidePaymentToleranceD = Subscription::factory()->paid()->create(['payment_tolerance_ends_at' => Carbon::yesterday()]);
        $this->assertCount(2, Subscription::withinPaymentTolerance()->get());
        $this->assertTrue($subscriptionWithinPaymentToleranceA->is(Subscription::withinPaymentTolerance()->get()[0]));
        $this->assertTrue($paidSubscriptionWithinPaymentToleranceB->is(Subscription::withinPaymentTolerance()->get()[1]));
        $this->assertTrue($subscriptionWithinPaymentToleranceA->is_within_payment_tolerance_time);
        $this->assertTrue($paidSubscriptionWithinPaymentToleranceB->is_within_payment_tolerance_time);
        $this->assertFalse($subscriptionOutsidePaymentToleranceC->is_within_payment_tolerance_time);
        $this->assertFalse($subscriptionOutsidePaymentToleranceD->is_within_payment_tolerance_time);
    }

    /** @test */
    public function can_get_testing_subscriptions(): void
    {
        $testingSubscriptionA = Subscription::factory()->testing()->create();
        $testingSubscriptionB = Subscription::factory()->testing()->create();
        $activeSubscriptionC = Subscription::factory()->unpaid()->create();
        $activeSubscriptionD = Subscription::factory()->active()->create();
        $this->assertCount(2, Subscription::testing()->get());
        $this->assertTrue($testingSubscriptionA->is(Subscription::testing()->get()[0]));
        $this->assertTrue($testingSubscriptionB->is(Subscription::testing()->get()[1]));
        $this->assertTrue($testingSubscriptionA->is_testing);
        $this->assertTrue($testingSubscriptionB->is_testing);
        $this->assertFalse($activeSubscriptionC->is_testing);
        $this->assertFalse($activeSubscriptionD->is_testing);
    }

    /** @test */
    public function can_get_upcoming_subscriptions_incl_testing(): void
    {
        $upcomingSubscriptionA = Subscription::factory()->upcoming()->create();
        $upcomingSubscriptionB = Subscription::factory()->upcoming()->create();
        $activeSubscriptionC = Subscription::factory()->unpaid()->create();
        $activeSubscriptionD = Subscription::factory()->active()->create();
        $testingSubscriptionE = Subscription::factory()->testing()->create();
        $testingSubscriptionF = Subscription::factory()->testing()->create();
        $this->assertCount(4, Subscription::upcoming()->get());
        $this->assertTrue($upcomingSubscriptionA->is(Subscription::upcoming()->get()[0]));
        $this->assertTrue($upcomingSubscriptionB->is(Subscription::upcoming()->get()[1]));
        $this->assertTrue($testingSubscriptionE->is(Subscription::upcoming()->get()[2]));
        $this->assertTrue($testingSubscriptionF->is(Subscription::upcoming()->get()[3]));
        $this->assertTrue($upcomingSubscriptionA->is_upcoming);
        $this->assertTrue($upcomingSubscriptionB->is_upcoming);
        $this->assertFalse($activeSubscriptionC->is_upcoming);
        $this->assertFalse($activeSubscriptionD->is_upcoming);
        $this->assertTrue($testingSubscriptionE->is_upcoming);
        $this->assertTrue($testingSubscriptionF->is_upcoming);
    }

    /** @test */
    public function can_get_regular_subscriptions(): void
    {
        Subscription::factory()->testing()->create();
        Subscription::factory()->testing()->create();
        Subscription::factory()->unpaid()->create();
        $activeSubscriptionD = Subscription::factory()->active()->create();
        $this->assertCount(1, Subscription::regular()->get());
        $this->assertTrue($activeSubscriptionD->is(Subscription::regular()->get()[0]));
    }

    /** @test */
    public function can_get_expiring_subscriptions(): void
    {
        $expiringSubscriptionA = Subscription::factory()->expiring()->create();
        $expiringSubscriptionB = Subscription::factory()->expiring()->create();
        $activeSubscriptionC = Subscription::factory()->active()->create([
            'expires_at' => Carbon::tomorrow()->endOfDay()->subSeconds(2)
        ]);
        $activeSubscriptionD = Subscription::factory()->active()->create([
            'expires_at' => Carbon::tomorrow()->endOfDay()->addSeconds(2)
        ]);
        $this->assertCount(2, Subscription::expiring()->get());
        $this->assertTrue($expiringSubscriptionA->is(Subscription::expiring()->get()[0]));
        $this->assertTrue($expiringSubscriptionB->is(Subscription::expiring()->get()[1]));
        $this->assertTrue($expiringSubscriptionA->is_expiring);
        $this->assertTrue($expiringSubscriptionB->is_expiring);
        $this->assertFalse($activeSubscriptionC->is_expiring);
        $this->assertFalse($activeSubscriptionD->is_expiring);
    }

    /** @test */
    public function can_get_recurring_subscriptions(): void
    {
        $recurringSubscriptionA = Subscription::factory()->recurring()->create();
        $recurringSubscriptionB = Subscription::factory()->recurring()->create();
        $nonRecurringSubscriptionC = Subscription::factory()->nonrecurring()->create();
        $nonRecurringSubscriptionD = Subscription::factory()->nonrecurring()->create();
        $this->assertCount(2, Subscription::recurring()->get());
        $this->assertTrue($recurringSubscriptionA->is(Subscription::recurring()->get()[0]));
        $this->assertTrue($recurringSubscriptionB->is(Subscription::recurring()->get()[1]));
        $this->assertTrue($recurringSubscriptionA->is_recurring);
        $this->assertTrue($recurringSubscriptionB->is_recurring);
        $this->assertFalse($nonRecurringSubscriptionC->is_recurring);
        $this->assertFalse($nonRecurringSubscriptionD->is_recurring);
    }

    /** @test */
    public function can_get_the_correct_remaining_days_of_a_subscription(): void
    {
        $subscription = Subscription::factory()->active()->create([
            'expires_at' => Carbon::parse('+ 3 weeks')
        ]);
        $this->assertEqualsWithDelta((int) Carbon::now()->diffInDays(Carbon::parse('+ 3 weeks')), $subscription->remaining_days, 1);

        $expiredSubscription = Subscription::factory()->expired()->create();
        $this->assertEquals(0, $expiredSubscription->remaining_days);
    }

    /** @test */
    public function can_not_get_remaining_days_of_an_unstarted_subscription(): void
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->testing()->create([
            'expires_at' => Carbon::parse('+ 8 weeks')
        ]);

        $this->assertFalse($subscription->has_started);

        try {
            $subscription->remaining_days;
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (SubscriptionException $e) {
            return;
        }

        $this->fail('expected a SubscriptionException');
    }

    /** @test
     * @throws SubscriptionException
     */
    public function can_cancel_immediately(): void
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->active()->create();
        $this->assertTrue($subscription->is_active);
        $subscription->cancel(true);
        $this->assertFalse($subscription->is_active);
        $this->assertTrue($subscription->is_cancelled);
    }

    /** @test
     * @throws SubscriptionException
     */
    public function can_cancel_at_the_end_of_the_subscription(): void
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->active()->create([
            'expires_at' => Carbon::parse('+ 1 week')
        ]);
        $this->assertTrue($subscription->is_active);
        /** @noinspection ArgumentEqualsDefaultValueInspection */
        $subscription->cancel(false);
        $this->assertEquals($subscription->expires_at, $subscription->cancelled_at);
        $this->assertTrue($subscription->is_active);
        $this->assertTrue($subscription->is_pending_cancellation);
        $this->assertFalse($subscription->is_cancelled);
    }

    /** @test */
    public function can_not_cancel_an_already_cancelled_subscription(): void
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->cancelled()->create();
        $this->assertTrue($subscription->is_cancelled);
        try {
            $subscription->cancel(true);
        } catch (SubscriptionException $e) {
            return;
        }

        $this->fail('expected SubscriptionException');
    }

    /** @test
     * @throws SubscriptionException
     */
    public function can_still_cancel_a_subscription_that_is_pending_cancellation(): void
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->active()->create();
        /** @noinspection ArgumentEqualsDefaultValueInspection */
        $subscription->cancel(false);

        $this->assertTrue($subscription->is_pending_cancellation);

        $subscription->cancel(true);

        $this->assertTrue($subscription->is_cancelled);
        $this->assertFalse($subscription->is_pending_cancellation);
    }

    /** @test */
    public function it_knows_whether_it_is_active(): void
    {
        $subscription = Subscription::factory()->active()->create();
        $this->assertTrue($subscription->is_active);
    }

    /** @test */
    public function it_know_its_feature_authorizations(): void
    {
        $plan = Plan::factory()->create();
        /** @var Subscription $subscription */
        $plan->features()->saveMany([
            new Feature([
                'name' => 'Limited feature',
                'code' => 'feature.limited',
                'description' => 'Some limited feature',
                'type' => 'limit',
                'limit' => 10,
            ]),
            new Feature([
                'name' => 'Feature Feature',
                'code' => 'feature.feature',
                'description' => 'Some feature feature',
                'type' => 'feature',
            ]),
            new Feature([
                'name' => 'Unlimited feature',
                'code' => 'feature.unlimited',
                'description' => 'Some unlimited feature',
                'type' => 'limit',
                'limit' => 0,
            ]),
        ]);
        $subscription = Subscription::factory()->active()->create(['plan_id' => $plan->id]);
        $this->assertEquals(['feature.feature'], $subscription->feature_authorizations);
    }

    /** @test */
    public function it_know_its_limits(): void
    {
        $plan = Plan::factory()->create();
        /** @var Subscription $subscription */
        $plan->features()->saveMany([
            new Feature([
                'name' => 'Limited feature',
                'code' => 'feature.limited',
                'description' => 'Some limited feature',
                'type' => 'limit',
                'restricted_model' => 'modelA',
                'restricted_relation' => 'relationA',
                'limit' => 10,
            ]),
            new Feature([
                'name' => 'Feature Feature',
                'code' => 'feature.feature',
                'description' => 'Some feature feature',
                'type' => 'feature',
            ]),
            new Feature([
                'name' => 'Unlimited feature',
                'code' => 'feature.unlimited',
                'description' => 'Some unlimited feature',
                'type' => 'limit',
                'limit' => 0,
                'restricted_model' => 'modelB',
                'restricted_relation' => 'relationB'
            ]),
        ]);
        $subscription = Subscription::factory()->active()->create(['plan_id' => $plan->id]);
        $limits = $subscription->limits;
        $this->assertCount(2, $limits);
        $this->assertEquals((object)[
            'code' => 'feature.limited',
            'available' => 10,
            'restricted_model' => 'modelA',
            'restricted_relation' => 'relationA'
        ], $limits[0]);
        $this->assertEquals((object)[
            'code' => 'feature.unlimited',
            'available' => 0,
            'restricted_model' => 'modelB',
            'restricted_relation' => 'relationB'
        ], $limits[1]);
    }

    /** @test
     * @throws FeatureNotFoundException
     */
    public function can_get_a_subscription_feature_by_code(): void
    {
        $plan = Plan::factory()->create();
        /** @var Subscription $subscription */
        $plan->features()->saveMany([
            new Feature([
                'name' => 'Limited feature',
                'code' => 'feature.limited',
                'description' => 'Some limited feature',
                'type' => 'limit',
                'limit' => 10,
            ]),
            new Feature([
                'name' => 'Feature Feature',
                'code' => 'feature.feature',
                'description' => 'Some feature feature',
                'type' => 'feature',
            ]),
            new Feature([
                'name' => 'Unlimited feature',
                'code' => 'feature.unlimited',
                'description' => 'Some unlimited feature',
                'type' => 'limit',
                'limit' => 0,
            ]),
        ]);
        $subscription = Subscription::factory()->active()->create(['plan_id' => $plan->id]);
        $this->assertEquals('Unlimited feature', $subscription->getFeatureByCode('feature.unlimited')->name);
    }
}
