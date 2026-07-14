<?php

namespace OnlineVerkaufen\Subscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Test\Models\User;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'code' => $this->faker->slug(),
            'type' => $this->faker->randomElement([Feature::TYPE_FEATURE, Feature::TYPE_LIMIT]),
            'limit' => $this->faker->numberBetween(2, 100),
            'restricted_model' => User::class,
            'restricted_relation' => 'posts',
            'position' => $this->faker->numberBetween(0, 10),
            'name' => 'Testing Feature ' . $this->faker->word,
            'description' => $this->faker->paragraph,
        ];
    }
}
