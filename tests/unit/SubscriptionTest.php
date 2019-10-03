<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use Carbon\Carbon;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
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
        $activeSubscriptionA = factory(Subscription::class)->states(['active'])->create();
        $activeSubscriptionB = factory(Subscription::class)->states(['testing'])->create();
        $activeSubscriptionC = factory(Subscription::class)->states(['tolerance'])->create();

        factory(Subscription::class)->states(['unpaid'])->create();
        factory(Subscription::class)->states(['expired'])->create();
        factory(Subscription::class)->states(['cancelled'])->create();
        factory(Subscription::class)->states(['refunded'])->create();

        $this->assertCount(3, Subscription::active()->get());
        $this->assertTrue($activeSubscriptionA->is(Subscription::active()->get()[0]));
        $this->assertTrue($activeSubscriptionB->is(Subscription::active()->get()[1]));
        $this->assertTrue($activeSubscriptionC->is(Subscription::active()->get()[2]));
    }

    /** @test */
    public function can_get_paid_subscriptions(): void
    {
        $paidSubscriptionA = factory(Subscription::class)->states(['paid'])->create();
        $paidSubscriptionB = factory(Subscription::class)->states(['paid'])->create();
        $unpaidSubscriptionC = factory(Subscription::class)->states(['unpaid'])->create();
        $unpaidSubscriptionD = factory(Subscription::class)->states(['unpaid'])->create();
        $this->assertCount(2, Subscription::paid()->get());
        $this->assertCount(2, Subscription::unpaid()->get());
        $this->assertTrue($paidSubscriptionA->is(Subscription::paid()->get()[0]));
        $this->assertTrue($paidSubscriptionB->is(Subscription::paid()->get()[1]));
        $this->assertTrue($unpaidSubscriptionC->is(Subscription::unpaid()->get()[0]));
        $this->assertTrue($unpaidSubscriptionD->is(Subscription::unpaid()->get()[1]));
    }

    /** @test */
    public function can_get_subscriptions_within_payment_tolerance(): void
    {
        $subscriptionWithinPaymentToleranceA = factory(Subscription::class)->states(['tolerance'])->create();
        $paidSubscriptionBWithinPaymentTolerance = factory(Subscription::class)->states(['paid'])->create(['payment_tolerance_ends_at' => Carbon::tomorrow()]);
        factory(Subscription::class)->states(['unpaid'])->create();
        factory(Subscription::class)->states(['paid'])->create(['payment_tolerance_ends_at' => Carbon::yesterday()]);
        $this->assertCount(2, Subscription::withinPaymentTolerance()->get());
        $this->assertTrue($subscriptionWithinPaymentToleranceA->is(Subscription::withinPaymentTolerance()->get()[0]));
        $this->assertTrue($paidSubscriptionBWithinPaymentTolerance->is(Subscription::withinPaymentTolerance()->get()[1]));

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
    }

    /** @test */
    public function can_get_regular_subscriptions(): void
    {
        $testingSubscriptionA = factory(Subscription::class)->states(['testing'])->create();
        $testingSubscriptionB = factory(Subscription::class)->states(['testing'])->create();
        $activeSubscriptionC = factory(Subscription::class)->states(['unpaid'])->create();
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
            'expires_at' => Carbon::tomorrow()->endOfDay()->subSecond(2)
        ]);
        $activeSubscriptionD = factory(Subscription::class)->states(['active'])->create([
            'expires_at' => Carbon::tomorrow()->endOfDay()->addSecond(2)
        ]);
        $this->assertCount(2, Subscription::expiring()->get());
        $this->assertTrue($expiringSubscriptionA->is(Subscription::expiring()->get()[0]));
        $this->assertTrue($expiringSubscriptionB->is(Subscription::expiring()->get()[1]));
    }

    /** @test */
    public function can_get_recurring_subscriptions(): void
    {
        $recurringSubscriptionA = factory(Subscription::class)->states(['recurring'])->create();
        $recurringSubscriptionB = factory(Subscription::class)->states(['recurring'])->create();
        factory(Subscription::class)->states(['nonrecurring'])->create();
        factory(Subscription::class)->states(['nonrecurring'])->create();
        $this->assertCount(2, Subscription::recurring()->get());
        $this->assertTrue($recurringSubscriptionA->is(Subscription::recurring()->get()[0]));
        $this->assertTrue($recurringSubscriptionB->is(Subscription::recurring()->get()[1]));
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

        $this->assertFalse($subscription->hasStarted());

        try {
            $subscription->remaining_days;
        } catch (SubscriptionException $e) {
            return;
        }

        $this->fail('expected a SubscriptionException');
    }

    /** @test */
    public function can_cancel_immediately(): void
    {
        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->states('active')->create();
        $this->assertTrue($subscription->isActive());
        $subscription->cancel(true);
        $this->assertFalse($subscription->isActive());
        $this->assertTrue($subscription->isCancelled());
    }

    /** @test */
    public function can_cancel_at_the_end_of_the_subscription(): void
    {
        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->states('active')->create([
            'expires_at' => Carbon::parse('+ 1 week')
        ]);
        $this->assertTrue($subscription->isActive());
        $subscription->cancel(false);
        $this->assertEquals($subscription->expires_at, $subscription->cancelled_at);
        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->isPendingCancellation());
        $this->assertFalse($subscription->isCancelled());
    }

    /** @test */
    public function can_not_cancel_an_already_cancelled_subscription(): void
    {
        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->states('cancelled')->create();
        $this->assertTrue($subscription->isCancelled());
        try {
            $subscription->cancel(true);
        } catch (SubscriptionException $e) {
            return;
        }

        $this->fail('expected SubscriptionException');
    }

    /** @test */
    public function can_still_cancel_a_subscription_that_is_pending_cancellation(): void
    {
        /** @var Subscription $subscription */
        $subscription = factory(Subscription::class)->states('active')->create();
        $subscription->cancel(false);

        $this->assertTrue($subscription->isPendingCancellation());

        $subscription->cancel(true);

        $this->assertTrue($subscription->isCancelled());
        $this->assertFalse($subscription->isPendingCancellation());
    }

    /** @test */
    public function it_knows_whether_it_is_active(): void
    {
        $subscription = factory(Subscription::class)->states('active')->create();
        $this->assertTrue($subscription->is_active);

    }
}
