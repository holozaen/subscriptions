<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use Carbon\Carbon;
use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class SubscriptionTest extends TestCase
{

    /** @test */
    public function it_knows_the_model_it_is_assigned_to(): void
    {
      $user = factory(User::class)->create();
      /** @var Subscription $subscription */
      $subscription = factory(Subscription::class)->create([
          'model_type' => User::class,
          'model_id' => $user->id
      ]);
      $this->assertTrue($subscription->model->is($user));
    }

    /** @test */
    public function can_get_all_active_subscriptions(): void
    {
        /** @var Subscription $activeSubscriptionA */
        $activeSubscriptionA = factory(Subscription::class)->states(['active'])->create();
        /** @var Subscription $activeSubscriptionB */
        $activeSubscriptionB = factory(Subscription::class)->states(['testing'])->create();
        /** @var Subscription $activeSubscriptionC */
        $activeSubscriptionC = factory(Subscription::class)->states(['tolerance'])->create();

        factory(Subscription::class)->states(['unpaid'])->create();
        factory(Subscription::class)->states(['expired'])->create();
        factory(Subscription::class)->states(['cancelled'])->create();
        factory(Subscription::class)->states(['refunded'])->create();

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
        $paidSubscriptionA = factory(Subscription::class)->states(['paid'])->create();
        /** @var Subscription $paidSubscriptionB */
        $paidSubscriptionB = factory(Subscription::class)->states(['paid'])->create();
        /** @var Subscription $unpaidSubscriptionC */
        $unpaidSubscriptionC = factory(Subscription::class)->states(['unpaid'])->create();
        /** @var Subscription $unpaidSubscriptionD */
        $unpaidSubscriptionD = factory(Subscription::class)->states(['unpaid'])->create();
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
        $subscriptionWithinPaymentToleranceA = factory(Subscription::class)->states(['tolerance'])->create();
        /** @var Subscription $paidSubscriptionWithinPaymentToleranceB */
        $paidSubscriptionWithinPaymentToleranceB = factory(Subscription::class)->states(['paid'])->create(['payment_tolerance_ends_at' => Carbon::tomorrow()]);
        /** @var Subscription $paidSubscriptionBWithinPaymentTolerance */
        $subscriptionOutsidePaymentToleranceC = factory(Subscription::class)->states(['unpaid'])->create(['payment_tolerance_ends_at' => Carbon::yesterday()]);
        /** @var Subscription $paidSubscriptionBWithinPaymentTolerance */
        $subscriptionOutsidePaymentToleranceD = factory(Subscription::class)->states(['paid'])->create(['payment_tolerance_ends_at' => Carbon::yesterday()]);
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
        $testingSubscriptionA = factory(Subscription::class)->states(['testing'])->create();
        $testingSubscriptionB = factory(Subscription::class)->states(['testing'])->create();
        $activeSubscriptionC = factory(Subscription::class)->states(['unpaid'])->create();
        $activeSubscriptionD = factory(Subscription::class)->states(['active'])->create();
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
        $upcomingSubscriptionA = factory(Subscription::class)->states(['upcoming'])->create();
        $upcomingSubscriptionB = factory(Subscription::class)->states(['upcoming'])->create();
        $activeSubscriptionC = factory(Subscription::class)->states(['unpaid'])->create();
        $activeSubscriptionD = factory(Subscription::class)->states(['active'])->create();
        $testingSubscriptionE = factory(Subscription::class)->states(['testing'])->create();
        $testingSubscriptionF = factory(Subscription::class)->states(['testing'])->create();
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
        factory(Subscription::class)->states(['testing'])->create();
        factory(Subscription::class)->states(['testing'])->create();
        factory(Subscription::class)->states(['unpaid'])->create();
        $activeSubscriptionD = factory(Subscription::class)->states(['active'])->create();
        $this->assertCount(1, Subscription::regular()->get());
        $this->assertTrue($activeSubscriptionD->is(Subscription::regular()->get()[0]));
    }

    /** @test */
    public function can_get_expiring_subscriptions(): void
    {
        $expiringSubscriptionA = factory(Subscription::class)->states(['expiring'])->create();
        $expiringSubscriptionB = factory(Subscription::class)->states(['expiring'])->create();
        $activeSubscriptionC = factory(Subscription::class)->states(['active'])->create([
            'expires_at' => Carbon::tomorrow()->endOfDay()->subSeconds(2)
        ]);
        $activeSubscriptionD = factory(Subscription::class)->states(['active'])->create([
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
        $recurringSubscriptionA = factory(Subscription::class)->states(['recurring'])->create();
        $recurringSubscriptionB = factory(Subscription::class)->states(['recurring'])->create();
        $nonRecurringSubscriptionC = factory(Subscription::class)->states(['nonrecurring'])->create();
        $nonRecurringSubscriptionD = factory(Subscription::class)->states(['nonrecurring'])->create();
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
        $subscription = factory(Subscription::class)->states('active')->create([
            'expires_at' => Carbon::parse('+ 3 weeks')
        ]);
        $this->assertEquals(Carbon::parse('+ 3 weeks')->diffInDays(Carbon::now()), $subscription->remaining_days);

        $expiredSubscription = factory(Subscription::class)->states('expired')->create();
        $this->assertEquals(0, $expiredSubscription->remaining_days);
    }

    /** @test */
    public function can_not_get_remaining_days_of_an_unstarted_subscription(): void
    {
        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->states('testing')->create([
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
        $subscription = factory(Subscription::class)->states('active')->create();
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
        $subscription = factory(Subscription::class)->states('active')->create([
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
        $subscription = factory(Subscription::class)->states('cancelled')->create();
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
        $subscription = factory(Subscription::class)->states('active')->create();
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
        $subscription = factory(Subscription::class)->states('active')->create();
        $this->assertTrue($subscription->is_active);
    }

    /** @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function it_knows_its_features(): void
    {
        $plan = factory(Plan::class)->create();
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
        $subscription = factory(Subscription::class)->states('active')->create(['plan_id' => $plan->id]);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals('feature.limited', $subscription->features->shift()->code);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals('feature.feature', $subscription->features->shift()->code);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEquals('feature.unlimited', $subscription->features->shift()->code);
        $this->assertEquals(10, $subscription->getRemainingOf('feature.limited'));
    }

    /**
     * @test
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function it_know_its_feature_usage_stats(): void
    {
        $plan = factory(Plan::class)->create();
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
            new Feature([
                'name' => 'Limited feature',
                'code' => 'feature.limited.scoped',
                'description' => 'Some limited feature with model scope',
                'type' => 'limit',
                'limit' => 2,
            ]),
        ]);
        $subscription = factory(Subscription::class)->states('active')->create(['plan_id' => $plan->id]);
        $subscription->consumeFeature('feature.limited.scoped', 1, 'some-model', 3);
        $this->assertEquals([
            [
                'code' => 'feature.limited',
                'type' => 'limited',
                'usage' => 0,
                'remaining' => 10
            ],
            [
                'code' => 'feature.limited.scoped',
                'type' => 'limited',
                'usage' => [
                    [
                        'model_type' => 'some-model',
                        'model_id' => 3,
                        'usage' => 1,
                        'remaining' => 1
                    ]
                ],
                'remaining' => null
            ],
            [
                'code' => 'feature.unlimited',
                'type' => 'unlimited',
                'usage' => 0,
            ],
        ], $subscription->feature_usage_stats);
    }

    /** @test */
    public function it_know_its_feature_authorizations(): void
    {
        $plan = factory(Plan::class)->create();
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
        $subscription = factory(Subscription::class)->states('active')->create(['plan_id' => $plan->id]);
        $this->assertEquals(['feature.feature'], $subscription->feature_authorizations);
    }
}
