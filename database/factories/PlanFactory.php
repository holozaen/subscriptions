<?php
/** @noinspection PhpUndefinedVariableInspection */

use OnlineVerkaufen\Subscriptions\Models\Plan;

$factory->define(Plan::class, function (\Faker\Generator $faker) {
    return [
        'position' => $faker->numberBetween(0, 10),
        'state' => $faker->randomElement([Plan::STATE_DISABLED, Plan::STATE_ACTIVE_INVISIBLE, Plan::STATE_ACTIVE]),
        'name' => 'Testing Plan '.$faker->randomElement(['Bronze', 'Silver', 'Gold']),
        'type' => $faker->randomElement([Plan::TYPE_YEARLY, Plan::TYPE_MONTHLY, Plan::TYPE_DURATION]),
        'description' => $faker->paragraph,
        'price' => $faker->randomElement([9900,29900,59900]),
        'currency' => 'CHF',
        'duration' => 30,
    ];
});
$factory->state(Plan::class, 'active', function() {
    return [
        'state' => Plan::STATE_ACTIVE
    ];
});
$factory->state(Plan::class, 'invisible', function() {
    return [
        'state' => Plan::STATE_ACTIVE_INVISIBLE
    ];
});
$factory->state(Plan::class, 'disabled', function() {
    return [
        'state' => Plan::STATE_DISABLED
    ];
});
$factory->state(Plan::class, 'yearly', function() {
    return [
        'type' => Plan::TYPE_YEARLY
    ];
});
$factory->state(Plan::class, 'monthly', function() {
    return [
        'type' => Plan::TYPE_MONTHLY
    ];
});
$factory->state(Plan::class, 'duration', function() {
    return [
        'type' => Plan::TYPE_DURATION
    ];
});

