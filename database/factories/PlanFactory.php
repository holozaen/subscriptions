<?php
/** @noinspection PhpUndefinedVariableInspection */

use OnlineVerkaufen\Subscriptions\Models\Plan;

$factory->define(Plan::class, static function (\Faker\Generator $faker) {
    return [
        'position' => $faker->numberBetween(0, 10),
        'state' => $faker->randomElement([Plan::STATE_DISABLED, Plan::STATE_ACTIVE]),
        'name' => 'Testing Plan '.$faker->randomElement(['Bronze', 'Silver', 'Gold']),
        'type' => $faker->randomElement(array_map(static function($type) { return $type['code']; }, Plan::PLAN_TYPES)),
        'description' => $faker->paragraph,
        'price' => $faker->randomElement([9900,29900,59900]),
        'currency' => 'CHF',
        'duration' => 30,
    ];
});
$factory->state(Plan::class, 'active', static function() {
    return [
        'state' => Plan::STATE_ACTIVE
    ];
});
$factory->state(Plan::class, 'disabled', static function() {
    return [
        'state' => Plan::STATE_DISABLED
    ];
});
$factory->state(Plan::class, 'yearly', static function() {
    return [
        'type' => 'yearly'
    ];
});
$factory->state(Plan::class, 'monthly', static function() {
    return [
        'type' => 'monthly'
    ];
});
$factory->state(Plan::class, 'duration', static function() {
    return [
        'type' => 'duration'
    ];
});

