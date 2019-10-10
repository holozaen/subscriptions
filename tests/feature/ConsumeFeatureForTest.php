<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\FeatureConsumed;
use OnlineVerkaufen\Subscriptions\Events\FeatureUnconsumed;
use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class ConsumeFeatureForTest extends TestCase
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

    /**
     * @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function can_consume_a_limited_for_feature(): void
    {
        $this->withoutExceptionHandling();
        Event::fake();
        $this->subscription->consumeFeature('feature.limited', 1, 'some-type', 1);
        $this->assertEquals($this->subscription->usages()->count(), 1);
        $this->assertEquals(1, $this->subscription->getUsageOf('feature.limited', 'some-type', 1));
        $this->assertEquals(9, $this->subscription->getRemainingOf('feature.limited', 'some-type', 1));
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(FeatureConsumed::class);
    }

     /**
     * @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function can_not_overconsume_a_limited_for_feature(): void
    {
        Event::fake();
        try {
            $this->subscription->consumeFeature('feature.limited', 11, 'some-type', 1);
        } catch (FeatureException $e) {
            $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited', 'some-type', 1));
            $this->assertEquals(10, $this->subscription->getRemainingOf('feature.limited', 'some-type', 1));
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(FeatureConsumed::class);
            return;
        }
        $this->fail('Expected FeatureException');
    }

    /**
     * @test
     * @throws FeatureException
     */
    public function can_not_consume_an_inexisting_feature(): void
    {
        Event::fake();
        try {
            $this->subscription->consumeFeature('other_feature', 1, 'some-type', 1);
        } catch (FeatureNotFoundException $e) {
            $this->assertEquals($this->subscription->usages()->count(), 0);
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(FeatureConsumed::class);
            return;
        }
        $this->fail('Expected FeatureNotFoundException');
    }

    /**
     * @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function can_unconsume_a_limited_feature(): void
    {
        $this->subscription->consumeFeature('feature.limited', 1, 'some-type', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        Event::fake();
        $this->subscription->unconsumeFeature('feature.limited', 1, 'some-type', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited', 'some-type', 1));
        $this->assertEquals(10, $this->subscription->getRemainingOf('feature.limited', 'some-type', 1));
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(FeatureUnconsumed::class);
        $this->subscription->unconsumeFeature('feature.limited', 1, 'some-type', 1);
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited', 'some-type', 1));
    }

    /**
     * @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function can_unconsume_an_unlimited_feature(): void
    {
        $this->subscription->consumeFeature('feature.unlimited', 1, 'some-type', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        Event::fake();
        $this->subscription->unconsumeFeature('feature.unlimited', 1, 'some-type', 1);
        $this->assertEquals(1, $this->subscription->usages()->count());
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.unlimited', 'some-type', 1));
        $this->assertEquals(9999, $this->subscription->getRemainingOf('feature.unlimited', 'some-type', 1));
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(FeatureUnconsumed::class);
    }

     /**
     * @test
     * @throws FeatureException
     */
    public function can_not_unconsume_an_inexisting_feature(): void
    {
        Event::fake();
        try {
            $this->subscription->unconsumeFeature('other_feature', 1, 'some-type', 1);
        } catch (FeatureNotFoundException $e) {
            $this->assertEquals($this->subscription->usages()->count(), 0);
            /** @noinspection PhpUndefinedMethodInspection */
            Event::assertNotDispatched(FeatureUnconsumed::class);
            return;
        }
        $this->fail('Expected FeatureNotFoundException');
    }
}
