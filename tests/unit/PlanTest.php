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
        $this->activePlanA = Plan::factory()->active()->create();
        $this->disabledPlanB = Plan::factory()->disabled()->create();
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
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create([
            'plan_id' => $plan->id
        ]);
        $this->assertCount(1, $plan->subscriptions);
        $this->assertTrue($plan->subscriptions()->first()->is($subscription));
    }

    /** @test */
    public function can_get_the_plan_type_definition_for_a_plan(): void
    {
        /** @var Plan $plan */
        $plan = Plan::factory()->yearly()->create();
        $this->assertEquals([
            'code' => 'yearly',
            'class' => Yearly::class
        ], $plan->plan_type_definition);
    }

    /** @test */
    public function can_get_the_plan_type_definition_for_a_specific_plan_code(): void
    {
        $this->assertEquals([
            'code' => 'yearly',
            'class' => Yearly::class
        ], Plan::getPlanTypeDefinitionForCode('yearly'));
    }

    /** @test */
    public function can_get_the_plan_type_date_processor_class_name(): void
    {
        /** @var Plan $plan */
        $plan = Plan::factory()->yearly()->create();
        $this->assertEquals(Yearly::class, $plan->plan_type_date_processor);
    }

    /** @test */
    public function can_get_the_plan_type_date_processor_class_for_a_specific_plan_code(): void
    {
        $this->assertEquals(Yearly::class, Plan::getPlanTypeDateProcessorClassForCode('yearly'));
    }
}
