<?php


namespace OnlineVerkaufen\Plan\Models\PlanTypes;


use Carbon\Carbon;
use Carbon\CarbonInterface;

class Yearly implements PlanType
{
    public function getExpirationDate(array $arguments = []): CarbonInterface
    {
        if (array_key_exists('testing_days', $arguments)) {
            return Carbon::now()->addDays($arguments['testing_days'])->addYear()->endOfDay();
        }
        return Carbon::now()->addYear()->endOfDay();
    }
}
