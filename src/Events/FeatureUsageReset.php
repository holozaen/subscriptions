<?php

namespace OnlineVerkaufen\Subscriptions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Subscription;

class FeatureUsageReset
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $feature;
    public $model_type;
    public $model_id;

    /**
     * @param Subscription $subscription Subscription on which action was done.
     * @param Feature $feature The feature that was consumed.
     * @param string|null $model_type
     * @param int|null $model_id
     */
    public function __construct($subscription, $feature, ?string $model_type, ?int $model_id)
    {
        $this->subscription = $subscription;
        $this->feature = $feature;
        $this->model_type = $model_type;
        $this->model_id = $model_id;
     }
}
