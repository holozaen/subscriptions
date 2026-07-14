<?php

namespace OnlineVerkaufen\Subscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnlineVerkaufen\Subscriptions\Models\Plan;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'position' => $this->faker->numberBetween(0, 10),
            'state' => $this->faker->randomElement([Plan::STATE_DISABLED, Plan::STATE_ACTIVE]),
            'name' => 'Testing Plan ' . $this->faker->randomElement(['Bronze', 'Silver', 'Gold']),
            'type' => $this->faker->randomElement(array_map(static function ($type) {
                return $type['code'];
            }, Plan::PLAN_TYPES)),
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomElement([9900, 29900, 59900]),
            'currency' => 'CHF',
            'duration' => 30,
        ];
    }

    public function active(): static
    {
        return $this->state(['state' => Plan::STATE_ACTIVE]);
    }

    public function disabled(): static
    {
        return $this->state(['state' => Plan::STATE_DISABLED]);
    }

    public function yearly(): static
    {
        return $this->state(['type' => 'yearly']);
    }

    public function monthly(): static
    {
        return $this->state(['type' => 'monthly']);
    }

    public function duration(): static
    {
        return $this->state(['type' => 'duration']);
    }
}
