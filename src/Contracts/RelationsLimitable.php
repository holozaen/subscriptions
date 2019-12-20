<?php


namespace OnlineVerkaufen\Subscriptions\Contracts;


use OnlineVerkaufen\Subscriptions\Models\Subscription;

interface RelationsLimitable
{
    public function getActiveSubscription(): ?Subscription;
}
