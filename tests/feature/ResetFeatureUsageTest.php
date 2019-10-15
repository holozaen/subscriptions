<?php

namespace OnlineVerkaufen\Subscriptions\Test\feature;

use Illuminate\Support\Facades\Event;
use OnlineVerkaufen\Subscriptions\Events\FeatureUsageReset;
use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class ResetFeatureUsageTest extends TestCase
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
            ])
            ]);
    }

    /**
     * @test
     * @throws FeatureNotFoundException
     * @throws FeatureException
     */
    public function can_reset_a_limit_feature_usage(): void
    {
        Event::fake();
        $this->subscription->consumeFeature('feature.limited', 1);
        $this->assertEquals($this->subscription->usages()->count(), 1);
        $this->assertEquals(1, $this->subscription->getUsageOf('feature.limited'));
        $this->assertEquals(9, $this->subscription->getRemainingOf('feature.limited'));
        $this->subscription->resetFeatureUsage('feature.limited');
        $this->assertEquals(0, $this->subscription->getUsageOf('feature.limited'));
        $this->assertEquals(10, $this->subscription->getRemainingOf('feature.limited'));
        /** @noinspection PhpUndefinedMethodInspection */
        Event::assertDispatched(FeatureUsageReset::class);
    }
}
