<?php /** @noinspection PhpUndefinedVariableInspection */

use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Plan;

$factory->define(Feature::class, function (\Faker\Generator $faker) {
    return [
        'plan_id' => function() {return factory(Plan::class)->create();},
        'code' => $faker->slug(),
        'type' => $faker->randomElement([Feature::TYPE_FEATURE, Feature::TYPE_LIMIT]),
        'limit' => $faker->numberBetween(2,100),
        'position' => $faker->numberBetween(0,10),
        'name' => 'Testing Feature '.$faker->word,
        'description' => $faker->paragraph,
    ];
});
