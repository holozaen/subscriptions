<?php

namespace OnlineVerkaufen\Plan\Test\unit;


use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\TestCase;

class SubscriptionTest extends TestCase
{
    /** @test */
    public function can_get_all_active_subscriptions(): void
    {
        $activeSubscriptionA = factory(Subscription::class)->states(['active'])->create();
        $activeSubscriptionB = factory(Subscription::class)->states(['testing', 'unpaid'])->create();
        $inactiveSubscriptionC = factory(Subscription::class)->states(['active', 'unpaid'])->create();
        $inactiveSubscriptionD = factory(Subscription::class)->states(['expired', 'paid'])->create();
        $inactiveSubscriptionE = factory(Subscription::class)->states(['cancelled', 'paid'])->create();
        $inactiveSubscriptionF = factory(Subscription::class)->states(['refunded', 'paid'])->create();

        $this->assertCount(2, Subscription::active()->get());
        $this->assertTrue($activeSubscriptionA->is(Subscription::active()->get()[0]));
        $this->assertTrue($activeSubscriptionB->is(Subscription::active()->get()[1]));
    }

    /** @test */
    public function can_get_paid_subscriptions()
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
}
