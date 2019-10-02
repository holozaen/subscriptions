<?php


namespace OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors;


use Carbon\CarbonInterface;

class Yearly extends AbstractPlanTypeDateProcessor
{
    public function getExpirationDate(): CarbonInterface
    {
        return $this->expirationDate->addYear();
    }
}
