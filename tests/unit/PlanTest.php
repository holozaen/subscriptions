<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors\Yearly;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class PlanTest extends TestCase
{
    /** @var Plan */
    private $activePlanA;

    /** @var Plan */
    private $disabledPlanB;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlanA = factory(Plan::class)->states(['active'])->create();
        $this->disabledPlanB = factory(Plan::class)->states(['disabled'])->create();
    }

    /** @test */
    public function can_get_the_active_plans(): void
    {
        $plans = Plan::active()->get();
        $this->assertCount(1, $plans);
        $this->assertTrue($plans->shift()->is($this->activePlanA));
    }

    /** @test */
    public function can_get_the_disabled_plans(): void
    {
        $plans = Plan::disabled()->get();
        $this->assertCount(1, $plans);
        $this->assertTrue($plans->shift()->is($this->disabledPlanB));
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

    /** @test */
    public function can_get_the_plan_type_definition_for_a_plan(): void
    {
        $plan = factory(Plan::class)->state('yearly')->create();
        $this->assertEquals([
            'code' => 'yearly',
            'class' => Yearly::class
        ], $plan->getPlanTypeDefinition());
    }

    /** @test */
    public function can_get_the_plan_type_definition_for_a_specific_plan_code(): void
    {
        $plan = app()->make(Plan::class);
        $this->assertEquals([
            'code' => 'yearly',
            'class' => Yearly::class
        ], $plan->getPlanTypeDefinition('yearly'));
    }

    /** @test */
    public function can_get_the_plan_type_date_processor_class(): void
    {
        $plan = factory(Plan::class)->state('yearly')->create();
        $this->assertEquals(Yearly::class, $plan->getPlanTypeDateProcessorClass());
    }

    /** @test */
    public function can_get_the_plan_type_date_processor_class_for_a_specific_plan_code(): void
    {
        $plan = app()->make(Plan::class);
        $this->assertEquals(Yearly::class, $plan->getPlanTypeDateProcessorClass('yearly'));
    }
}
