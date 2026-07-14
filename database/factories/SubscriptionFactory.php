<?php

namespace OnlineVerkaufen\Subscriptions\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'model_id' => User::factory(),
            'model_type' => User::class,
            'price' => $this->faker->randomElement([9900, 29900, 59900]),
            'currency' => 'CHF',
            'is_recurring' => $this->faker->randomElement([true, false]),
            'payment_tolerance_ends_at' => Carbon::now(),
            'starts_at' => Carbon::now(),
            'expires_at' => Carbon::parse('+10 days'),
            'test_ends_at' => Carbon::now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['paid_at' => Carbon::now()]);
    }

    public function unpaid(): static
    {
        return $this->state(['paid_at' => null]);
    }

    public function tolerance(): static
    {
        return $this->state([
            'starts_at' => Carbon::parse('-10 days'),
            'expires_at' => Carbon::parse('+10 days'),
            'paid_at' => null,
            'payment_tolerance_ends_at' => Carbon::now()->addDays(2),
        ]);
    }

    public function recurring(): static
    {
        return $this->state(['is_recurring' => true]);
    }

    public function nonrecurring(): static
    {
        return $this->state(['is_recurring' => false]);
    }

    public function active(): static
    {
        return $this->state([
            'starts_at' => Carbon::parse('-10 days'),
            'expires_at' => Carbon::parse('+10 days'),
            'paid_at' => Carbon::now(),
            'payment_tolerance_ends_at' => Carbon::yesterday(),
        ]);
    }

    public function testing(): static
    {
        return $this->state([
            'test_ends_at' => Carbon::parse('+10 days'),
            'starts_at' => Carbon::parse('+10 days'),
            'expires_at' => Carbon::parse('+20 days'),
        ]);
    }

    public function expiring(): static
    {
        return $this->state([
            'paid_at' => Carbon::parse('-40 days'),
            'starts_at' => Carbon::parse('-30 days'),
            'expires_at' => Carbon::tomorrow()->endOfDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'paid_at' => Carbon::parse('-40 days'),
            'starts_at' => Carbon::parse('-30 days'),
            'expires_at' => Carbon::parse('-1 days'),
            'payment_tolerance_ends_at' => Carbon::parse('-30 days'),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state([
            'starts_at' => Carbon::parse('+10 days'),
            'expires_at' => Carbon::parse('+20 days'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'starts_at' => Carbon::parse('-30 days'),
            'expires_at' => Carbon::parse('+30 days'),
            'cancelled_at' => Carbon::parse('-1 days'),
        ]);
    }

    public function refunded(): static
    {
        return $this->state([
            'starts_at' => Carbon::parse('-30 days'),
            'expires_at' => Carbon::parse('+30 days'),
            'refunded_at' => Carbon::parse('-1 days'),
        ]);
    }
}
