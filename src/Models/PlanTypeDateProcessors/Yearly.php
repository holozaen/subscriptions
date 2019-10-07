<?php


namespace OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors;


use Carbon\CarbonInterface;

class Yearly extends AbstractPlanTypeDateProcessor
{
    public function getExpirationDate(): CarbonInterface
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->expirationDate->addYear();
    }
}
