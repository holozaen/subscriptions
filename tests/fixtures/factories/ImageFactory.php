<?php /** @noinspection PhpUndefinedVariableInspection */

$factory->define(\OnlineVerkaufen\Subscriptions\Test\Models\Image::class, static function (\Faker\Generator $faker) {
    return [
        'imageable_id' => null,
        'imageable_type' => null,
        'name' => $faker->word,
        'path' => $faker->word
    ];
});
