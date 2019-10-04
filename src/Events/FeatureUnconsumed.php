<?php

namespace OnlineVerkaufen\Subscriptions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Subscription;

class FeatureUnconsumed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $feature;
    public $used;
    public $remaining;

    /**
     * @param Model | Subscription $subscription Subscription on which action was done.
     * @param Feature $feature The feature that was consumed.
     * @param int $used The amount used on this unconsumption.
     * @param int $remaining The amount remaining for this feature.
     * @return void
     */
    public function __construct($subscription, Feature $feature, int $used, int $remaining)
    {
        $this->subscription = $subscription;
        $this->feature = $feature;
        $this->used = $used;
        $this->remaining = $remaining;
    }
}