<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class PlanTest extends TestCase
{
    private $activePlanA;
    private $invisiblePlanB;
    private $disabledPlanC;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlanA = factory(Plan::class)->states(['active'])->create();
        $this->invisiblePlanB = factory(Plan::class)->states(['invisible'])->create();
        $this->disabledPlanC = factory(Plan::class)->states(['disabled'])->create();
    }

    /** @test */
    public function can_get_the_active_plans(): void
    {
        $plans = Plan::active()->get();
        $this->assertCount(2, $plans);
        $this->assertTrue($plans->shift()->is($this->activePlanA));
        $this->assertTrue($plans->shift()->is($this->invisiblePlanB));
    }

    /** @test */
    public function can_get_the_visible_plans(): void
    {
        $plans = Plan::visible()->get();
        $this->assertCount(1, $plans);
        $this->assertTrue($plans->shift()->is($this->activePlanA));
    }

    /** @test */
    public function can_get_the_disabled_plans(): void
    {
        $plans = Plan::disabled()->get();
        $this->assertCount(1, $plans);
        $this->assertTrue($plans->shift()->is($this->disabledPlanC));
    }

    /** @test */
    public function can_get_the_subscriptions_to_a_plan(): void
    {
        $plan = factory(Plan::class)->create();
        $subscription = factory(Subscription::class)->create([
            'plan_id' => $plan->id
        ]);
        $this->assertCount(1, $plan->subscriptions);
        $this->assertTrue($plan->subscriptions()->first()->is($subscription));
    }

}
