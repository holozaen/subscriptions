<?php

namespace OnlineVerkaufen\Plan\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionMigrated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $previousSubscription;

    public function __construct($previousSubscription, $subscription)
    {
        $this->previousSubscription = $previousSubscription;
        $this->subscription = $subscription;
    }
}
