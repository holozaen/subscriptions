<?php

namespace OnlineVerkaufen\Subscriptions\Test\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnlineVerkaufen\Subscriptions\Test\Models\Post;
use OnlineVerkaufen\Subscriptions\Test\Models\User;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->word,
            'body' => $this->faker->paragraph,
        ];
    }
}
