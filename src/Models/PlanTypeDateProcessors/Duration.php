<?php


namespace OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors;


use Carbon\CarbonInterface;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;

class Duration extends AbstractPlanTypeDateProcessor
{
    private int|null $duration;

    public function __construct($testingDays = null, $startAt = null, $duration = null)
    {
        $this->duration = $duration;
        parent::__construct($testingDays,$startAt);
    }

    /**
     * @return CarbonInterface
     * @throws SubscriptionException
     */
    public function getExpirationDate(): CarbonInterface
    {
        if ($this->duration < 1) {
            throw new SubscriptionException('duration must be a positive integer');
        }
        return $this->expirationDate->addDays($this->duration);
    }
}
