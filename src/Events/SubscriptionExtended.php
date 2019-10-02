<?php

namespace OnlineVerkaufen\Plan\Events;

use Illuminate\Queue\SerializesModels;

class SubscriptionExtended
{
    use SerializesModels;

    public $model;
    public $subscription;

    public function __construct($model, $subscription)
    {
        $this->model = $model;
        $this->subscription = $subscription;
    }
}
