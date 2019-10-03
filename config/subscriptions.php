<?php

use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Plan;
use OnlineVerkaufen\Subscriptions\Models\Subscription;

return [

    'models' => [
        'plan' => Plan::class,
        'subscription' => Subscription::class,
        'feature' => Feature::class,
        'usage' => Feature\Usage::class,
    ],

    'paymentToleranceDays' => env('SUBSCRIPTIONS_PAYMENT_TOLERANCE_DAYS', 0)
];
