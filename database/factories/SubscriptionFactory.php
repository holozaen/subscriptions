<?php /** @noinspection PhpUndefinedVariableInspection */

use Carbon\Carbon;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Models\User;

$factory->define(Subscription::class, static function (\Faker\Generator $faker) {
    return [
        'plan_id' => static function() { return factory(Plan::class)->create()->id;},
        'model_id' => static function() { return factory(User::class)->create()->id;},
        'model_type' => User::class,
        'price' => $faker->randomElement([9900,29900,59900]),
        'currency' => 'CHF',
        'is_recurring' => $faker->randomElement([true,false]),
        'payment_tolerance_ends_at' => Carbon::now(),
        'starts_at' => Carbon::now(),
        'expires_at' => Carbon::parse('+10 days'),
        'test_ends_at' => Carbon::now()
 ];
});

$factory->state(Subscription::class, 'paid', static function() {
    return [
        'paid_at' => Carbon::now()
    ];
});

$factory->state(Subscription::class, 'unpaid', static function() {
    return [
        'paid_at' => null
    ];
});

$factory->state(Subscription::class, 'tolerance', static function() {
    return [
        'starts_at' => Carbon::parse('-10 days'),
        'expires_at' => Carbon::parse('+10 days'),
        'paid_at' => null,
        'payment_tolerance_ends_at' => Carbon::now()->addDays(2)
    ];
});

$factory->state(Subscription::class, 'recurring', static function() {
    return [
        'is_recurring' => true
    ];
});

$factory->state(Subscription::class, 'nonrecurring', static function() {
    return [
        'is_recurring' => false
    ];
});

$factory->state(Subscription::class, 'active', static function() {
    return [
        'starts_at' => Carbon::parse('-10 days'),
        'expires_at' => Carbon::parse('+10 days'),
        'paid_at' => Carbon::now(),
        'payment_tolerance_ends_at' => Carbon::yesterday()
    ];
});
$factory->state(Subscription::class, 'testing', static function() {
    return [
        'test_ends_at' => Carbon::parse('+10 days'),
        'starts_at' => Carbon::parse('+10 days'),
        'expires_at' => Carbon::parse('+20 days'),
  ];
});
$factory->state(Subscription::class, 'expiring', static function() {
    return [
        'paid_at' => Carbon::parse('-40 days'),
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::tomorrow()->endOfDay(),
    ];
});

$factory->state(Subscription::class, 'expired', static function() {
    return [
        'paid_at' => Carbon::parse('-40 days'),
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::parse('-1 days'),
        'payment_tolerance_ends_at' => Carbon::parse('-30 days')
    ];
});
$factory->state(Subscription::class, 'upcoming', static function() {
    return [
        'starts_at' => Carbon::parse('+10 days'),
        'expires_at' => Carbon::parse('+20 days')
    ];
});
$factory->state(Subscription::class, 'cancelled', static function() {
    return [
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::parse('+30 days'),
        'cancelled_at' => Carbon::parse('-1 days')
    ];
});
$factory->state(Subscription::class, 'refunded', static function() {
    return [
        'starts_at' => Carbon::parse('-30 days'),
        'expires_at' => Carbon::parse('+30 days'),
        'refunded_at' => Carbon::parse('-1 days')
    ];
});


