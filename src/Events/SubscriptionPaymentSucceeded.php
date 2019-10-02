<?php

namespace OnlineVerkaufen\Plan\Events;

use Illuminate\Queue\SerializesModels;

class SubscriptionPaymentSucceeded
{
    use SerializesModels;

    public $subscription;

    public function __construct($subscription)
    {
        $this->subscription = $subscription;
    }
}
