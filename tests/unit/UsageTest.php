<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Feature\Usage;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

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

    /** @throws SubscriptionException */
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

    /**
     * @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function can_get_the_corresponding_subscription(): void
    {
        $this->subscription->consumeFeature('feature.limited', 5);
        /** @var Usage $usage */
        $usage = Usage::first();
        $this->assertTrue($usage->subscription->is($this->subscription));
    }
}
