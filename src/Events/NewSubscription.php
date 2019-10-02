<?php

namespace OnlineVerkaufen\Plan\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSubscription
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $model;
    public $subscription;

    public function __construct($subscription)
    {
        $this->subscription = $subscription;
    }
}
