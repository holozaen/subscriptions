<?php

namespace OnlineVerkaufen\Plan\Events;

use Illuminate\Queue\SerializesModels;

class SubscriptionMigrated
{
    use SerializesModels;

    public $model;
    public $subscription;
    public $previousSubscription;

    public function __construct($model, $previousSubscription, $subscription)
    {
        $this->model = $model;
        $this->previousSubscription = $previousSubscription;
        $this->subscription = $subscription;
    }
}
