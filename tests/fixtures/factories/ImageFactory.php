<?php

namespace OnlineVerkaufen\Subscriptions\Test\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnlineVerkaufen\Subscriptions\Test\Models\Image;

class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        return [
            'imageable_id' => null,
            'imageable_type' => null,
            'name' => $this->faker->word,
            'path' => $this->faker->word,
        ];
    }
}
