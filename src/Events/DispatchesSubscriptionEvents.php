<?php


namespace OnlineVerkaufen\Subscriptions\Events;


trait DispatchesSubscriptionEvents
{
    /** @var bool  */
    private $canDispatch = true;

    public function dispatchSubscriptionEvent($event): void
    {
        if ($this->canDispatch) {
           event($event);
        }
    }

    public function muteSubscriptionEventDispatcher(): void
    {
        $this->canDispatch = false;
    }

    public function resumeSubscriptionEventDispatcher(): void
    {
        $this->canDispatch = true;
    }
}
