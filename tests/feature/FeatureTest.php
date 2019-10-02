<?php

namespace OnlineVerkaufen\Plan\Test\feature;

use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Plan\Events\FeatureConsumed;
use OnlineVerkaufen\Plan\Events\FeatureUnconsumed;
use OnlineVerkaufen\Plan\Exception\FeatureException;
use OnlineVerkaufen\Plan\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\TestCase;

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

    /** @test */
    public function can_consume_a_limited_feature(): void
    {
        Event::fake();
        $this->subscription->consumeFeature('feature.limited', 1);
        $this->assertEquals($this->subscription->usages()->count(), 1);
        $this->assertEquals(1, $this->subscription->getUsageOf('feature.limited'));
        $this->assertEquals(9, $this->subscription->getRemainingOf('feature.limited'));
        Event::assertDispatched(FeatureConsumed::class);
    }

    /** @test */
    public function can_consume_an_unlimited_feature(): void
    {
        Event::fake();
        $this->subscription->consumeFeature('feature.unlimited', 1);
        $this->assertEquals($this->subscription->usages()->count(), 1);
        $this->assertEquals(1, $this->subscription->getUsageOf('feature.unlimited'));
        $this->assertEquals(9999, $this->subscription->getRemainingOf('feature.unlimited'));
        Event::assertDispatched(FeatureConsumed::class);
    }

    /** @test */
    public function can_not_consume_a_general_feature(): void
    {
        Event::fake();
        try{
            $this->subscription->consumeFeature('feature.feature', 1);
        } catch (FeatureException $e) {
            $this->assertEquals($this->subscription->usages()->count(), 0);
            Event::assertNotDispatched(FeatureConsumed::class);
            return;
        }
        $this->fail('Expected FeatureException');
    }

    /** @test */
    public function can_not_overconsume_a_limited_feature(): void
    {
        Event::fake();
        try {
            $this->subscription->consumeFeature('feature.limited', 11);
        } catch (FeatureException $e) {
            $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited'));
            $this->assertEquals(10, $this->subscription->getRemainingOf('feature.limited'));
            Event::assertNotDispatched(FeatureConsumed::class);
            return;
        }
        $this->fail('Expected FeatureException');
    }

    /** @test */
    public function can_not_consume_an_inexisting_feature(): void
    {
        Event::fake();
        try {
            $this->subscription->consumeFeature('other_feature', 1);
        } catch (FeatureNotFoundException $e) {
            $this->assertEquals($this->subscription->usages()->count(), 0);
            Event::assertNotDispatched(FeatureConsumed::class);
            return;
        }
        $this->fail('Expected FeatureNotFoundException');
    }

    /** @test */
    public function can_unconsume_a_limited_feature(): void
    {
        $this->subscription->consumeFeature('feature.limited', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        Event::fake();
        $this->subscription->unconsumeFeature('feature.limited', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited'));
        $this->assertEquals(10, $this->subscription->getRemainingOf('feature.limited'));
        Event::assertDispatched(FeatureUnconsumed::class);
        $this->subscription->unconsumeFeature('feature.limited', 1);
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited'));
    }

    /** @test */
    public function can_unconsume_an_unlimited_feature(): void
    {
        $this->subscription->consumeFeature('feature.unlimited', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        Event::fake();
        $this->subscription->unconsumeFeature('feature.unlimited', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.unlimited'));
        $this->assertEquals(9999, $this->subscription->getRemainingOf('feature.unlimited'));
        Event::assertDispatched(FeatureUnconsumed::class);
    }

    /** @test */
    public function can_not_unconsume_a_feature_feature(): void
    {
        Event::fake();
        try {
            $this->subscription->unconsumeFeature('feature.feature', 1);
        } catch (FeatureException $e) {
            Event::assertNotDispatched(FeatureUnconsumed::class);
            return;
        }
        $this->fail('Expected FeatureNotFoundException');
    }
}
