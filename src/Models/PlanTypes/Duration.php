<?php


namespace OnlineVerkaufen\Plan\Models\PlanTypes;


use Carbon\Carbon;
use Carbon\CarbonInterface;
use OnlineVerkaufen\Plan\Exception\PlanException;

class Duration implements PlanType
{

    public function getExpirationDate(array $arguments = []): CarbonInterface
    {
        if (array_key_exists('days', $arguments)) {
            throw new PlanException("mandatory argument 'days' missing");
        }
        return Carbon::now()->addDays($arguments['duration'])->endOfDay();
    }
}
