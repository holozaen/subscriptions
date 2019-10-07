<?php /** @noinspection PhpUndefinedVariableInspection */

use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;

$factory->define(Feature::class, static function (\Faker\Generator $faker) {
    return [
        'plan_id' => static function() {return factory(Plan::class)->create();},
        'code' => $faker->slug(),
        'type' => $faker->randomElement([Feature::TYPE_FEATURE, Feature::TYPE_LIMIT]),
        'limit' => $faker->numberBetween(2,100),
        'position' => $faker->numberBetween(0,10),
        'name' => 'Testing Feature '.$faker->word,
        'description' => $faker->paragraph,
    ];
});
