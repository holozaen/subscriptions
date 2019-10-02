<?php

use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Plan;
use OnlineVerkaufen\Plan\Models\Subscription;

return [

    'models' => [
        'plan' => Plan::class,
        'subscription' => Subscription::class,
        'feature' => Feature::class,
        'usage' => Feature\Usage::class,
    ],

];
