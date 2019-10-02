<?php

namespace OnlineVerkaufen\Plan\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Subscription;

class FeatureConsumed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $feature;
    public $used;
    public $remaining;

    /**
     * @param Subscription $subscription Subscription on which action was done.
     * @param Feature $feature The feature that was consumed.
     * @param int $used The amount used on this consumption.
     * @param int $remaining The amount remaining for this feature.
     * @return void
     */
    public function __construct($subscription, $feature, int $used, int $remaining)
    {
        $this->subscription = $subscription;
        $this->feature = $feature;
        $this->used = $used;
        $this->remaining = $remaining;
    }
}
