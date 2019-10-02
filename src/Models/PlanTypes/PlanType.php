<?php


namespace OnlineVerkaufen\Plan\Models\PlanTypes;



use Carbon\CarbonInterface;

interface PlanType
{
    public function getExpirationDate(array $arguments = []): CarbonInterface;
}
