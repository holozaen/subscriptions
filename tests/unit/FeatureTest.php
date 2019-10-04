<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Feature\Usage;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class FeatureTest extends TestCase
{
    /** @var Subscription */
    private $subscription;

    public function setUp(): void
    {
        parent::setUp();
        $this->subscription = factory(Subscription::class)->states(['active'])->create();
        $this->subscription->features()->saveMany([
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
    }

    /** @test */
    public function it_knows_its_plan(): void
    {
        $newFeature = Feature::create([
            'plan_id' => $this->subscription->plan_id,
            'name' => 'New Feature',
            'code' => 'feature.new',
            'description' => 'Some new feature',
            'type' => 'limit',
            'limit' => 10
        ]);

        $this->assertTrue($newFeature->plan->is($this->subscription->plan));
    }

    /** @test */
    public function counts_are_correct(): void
    {
        $this->assertEquals($this->subscription->features()->count(), 3);
        $this->assertEquals($this->subscription->features()->limited()->count(), 1);
        $this->assertEquals($this->subscription->features()->unlimited()->count(), 1);
        $this->assertEquals($this->subscription->features()->feature()->count(), 1);
        $this->assertEquals($this->subscription->usages()->count(), 0);
    }

    /** @test */
    public function info_on_limited_feature_is_correct(): void
    {
        /** @var Feature $unlimitedFeature */
        $unlimitedFeature = $this->subscription->features()->code('feature.limited')->first();
        $this->assertTrue($unlimitedFeature->isLimitType());
        $this->assertTrue($unlimitedFeature->isLimited());
        $this->assertFalse($unlimitedFeature->isUnlimited());

    }

    /** @test */
    public function info_on_unlimited_feature_is_correct(): void
    {
        /** @var Feature $unlimitedFeature */
        $unlimitedFeature = $this->subscription->features()->code('feature.unlimited')->first();
        $this->assertTrue($unlimitedFeature->isLimitType());
        $this->assertFalse($unlimitedFeature->isLimited());
        $this->assertTrue($unlimitedFeature->isUnlimited());
    }
}