<?php

namespace OnlineVerkaufen\Plan\Test\unit;


use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Feature\Usage;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\Models\User;
use OnlineVerkaufen\Plan\Test\TestCase;

class UsageTest extends TestCase
{
    /** @var Plan */
    private $plan;

    /** @var User */
    private $user;
    /**
     * @var Subscription
     */
    private $subscription;

    /** @var Feature */
    private $feature;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->plan = factory(Plan::class)->create();
        $this->feature = factory(Feature::class)->create([
            'code' => 'feature.limited',
            'type' => Feature::TYPE_LIMIT,
            'plan_id' => $this->plan->id,
            'limit' => 10
        ]);
        $this->subscription = $this->user->subscribeTo($this->plan, true, false,10);
    }

    /** @test */
    public function can_get_the_corresponding_subscription(): void
    {
        $this->subscription->consumeFeature('feature.limited', 5);
        /** @var Usage $usage */
        $usage = Usage::first();
        $this->assertTrue($usage->subscription->is($this->subscription));
    }
}
