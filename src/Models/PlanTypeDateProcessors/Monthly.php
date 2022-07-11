<?php


namespace OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors;


use Carbon\CarbonInterface;

class Monthly extends AbstractPlanTypeDateProcessor
{

    public function getExpirationDate(): CarbonInterface
    {
        return $this->expirationDate->addMonth();
    }
}
