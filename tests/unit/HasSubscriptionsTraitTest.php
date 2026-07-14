<?php

namespace OnlineVerkaufen\Subscriptions\Test\unit;


use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;
use OnlineVerkaufen\Subscriptions\Test\TestCase;

class HasSubscriptionsTraitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function active_or_last_subscription_returns_active_subscription_if_exists(): void
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->active()->create();
        /** @var User $user */
        $user = $subscription->model;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertTrue($user->active_or_last_subscription->is($subscription));
    }

    /** @test */
    public function active_or_last_subscription_returns_last_past_subscription_if_no_active_subscription_exists(): void
    {
        /** @var Subscription $subscription */
        $latestExpiredSubscription = Subscription::factory()->expired()->create(['expires_at' => Carbon::parse('-1 weeks')]);
        Subscription::factory()->expired()->create(['expires_at' => Carbon::parse('-2 weeks')]);
        /** @var User $user */
        $user = $latestExpiredSubscription->model;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertTrue($user->active_or_last_subscription->is($latestExpiredSubscription));
    }

    /** @test */
    public function it_knows_whether_it_has_an_active_subscription(): void
    {
        /** @var Subscription $subscription */
        $expiredSubscription = Subscription::factory()->expired()->create();
        /** @var User $user */
        $user = $expiredSubscription->model;
        $this->assertFalse($user->hasActiveSubscription());
        Subscription::factory()->active()->create([
            'model_type' => User::class,
            'model_id' => $user->id
        ]);
        $this->assertTrue($user->hasActiveSubscription());
    }

    /** @test */
    public function it_knows_whether_it_has_unpaid_subscriptions(): void
    {
        /** @var Subscription $subscription */
        $paidSubscription = Subscription::factory()->active()->create();
        /** @var User $user */
        $user = $paidSubscription->model;
        $this->assertFalse($user->hasUnpaidSubscriptions());
        Subscription::factory()->unpaid()->create([
            'model_type' => User::class,
            'model_id' => $user->id
        ]);
        $this->assertTrue($user->hasUnpaidSubscriptions());
    }

    /** @test */
    public function it_knows_whether_it_has_upcoming_subscriptions_including_current_testing(): void
    {
        /** @var Subscription $subscription */
        $activeSubscription = Subscription::factory()->active()->create();
        /** @var User $user */
        $user = $activeSubscription->model;
        $this->assertFalse($user->hasUpcomingSubscription());
        Subscription::factory()->testing()->create([
            'model_type' => User::class,
            'model_id' => $user->id
        ]);
        $this->assertTrue($user->hasUpcomingSubscription());
    }

}
