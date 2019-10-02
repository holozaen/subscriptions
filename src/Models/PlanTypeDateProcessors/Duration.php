<?php


namespace OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors;


use Carbon\CarbonInterface;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;

class Duration extends AbstractPlanTypeDateProcessor
{
    /**
     * @var null | int
     */
    private $duration;

    public function __construct($testingDays = null, $startAt = null, $duration = null)
    {
        $this->duration = $duration;
        parent::__construct($testingDays,$startAt);
    }

    public function getExpirationDate(): CarbonInterface
    {
        if ($this->duration < 1) {
            throw new SubscriptionException('duration must be a positive integer');
        }
        return $this->expirationDate->addDays($this->duration);
    }
}
