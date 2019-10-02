<?php


namespace OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors;


use Carbon\CarbonInterface;

class Monthly extends AbstractPlanTypeDateProcessor
{

    public function getExpirationDate(): CarbonInterface
    {
        $expirationDate = $this->expirationDate->addMonth();
        return $expirationDate;
    }
}
