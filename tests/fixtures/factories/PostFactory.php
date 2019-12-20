<?php /** @noinspection PhpUndefinedVariableInspection */

use OnlineVerkaufen\Subscriptions\Test\Models\User;

$factory->define(\OnlineVerkaufen\Subscriptions\Test\Models\Post::class, static function (\Faker\Generator $faker) {
    return [
        'user_id' => static function() {return factory(User::class)->create();},
        'title' => $faker->word,
        'body' => $faker->paragraph
    ];
});
