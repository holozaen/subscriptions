<?php /** @noinspection PhpUndefinedVariableInspection */

use Carbon\Carbon;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;
use OnlineVerkaufen\Plan\Test\Models\User;

$factory->define(Subscription::class, function (\Faker\Generator $faker) {
    return [
        'plan_id' => function() { return factory(Plan::class)->create()->id;},
        'model_id' => function() { return factory(User::class)->create()->id;},
        'model_type' => User::class,
        'price' => $faker->randomElement([9900,29900,59900]),
        'currency' => 'CHF',
        'is_recurring' => $faker->randomElement([true,false]),
    ];
});

$factory->state(Subscription::class, 'paid', function() {
    return [
        'paid_at' => Carbon::now()
    ];
});

$factory->state(Subscription::class, 'unpaid', function() {
    return [
        'paid_at' => null
    ];
});

$factory->state(Subscription::class, 'active', function() {
    return [
        'starts_at' => Carbon::parse('-10 days'),
        'expires_at' => Carbon::parse('+10 days'),
        'paid_at' => Carbon::now()
    ];
});
$factory->state(Subscription::class, 'testing', function() {
    return [
        'test_ends_at' => Carbon::parse('+10 days'),
        'starts_at' => Carbon::parse('+10 days')
    ];
});
$factory->state(Subscription::class, 'expired', function() {
    return [
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::parse('-1 days')
    ];
});
$factory->state(Subscription::class, 'upcoming', function() {
    return [
        'starts_at' => Carbon::parse('+10 days'),
        'expires_at' => Carbon::parse('+20 days')
    ];
});
$factory->state(Subscription::class, 'cancelled', function() {
    return [
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::parse('+30 days'),
        'cancelled_at' => Carbon::parse('-1 days')
    ];
});
$factory->state(Subscription::class, 'refunded', function() {
    return [
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::parse('+30 days'),
        'refunded_at' => Carbon::parse('-1 days')
    ];
});


