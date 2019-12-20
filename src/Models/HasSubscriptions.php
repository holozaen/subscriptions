<?php

namespace OnlineVerkaufen\Subscriptions\Models;

use Carbon\Carbon;
use Exception;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use OnlineVerkaufen\Subscriptions\Events\DispatchesSubscriptionEvents;
use OnlineVerkaufen\Subscriptions\Events\NewSubscription;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionRenewed;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionCancelled;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionExtended;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionMigrated;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors\AbstractPlanTypeDateProcessor;

trait HasSubscriptions
{
    use DispatchesSubscriptionEvents;

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(config('subscriptions.models.subscription'), 'model');
    }

    private function activeSubscription()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->subscriptions()->active()->first();
    }

    public function getActiveSubscriptionAttribute()
    {
        return $this->activeSubscription();
    }

    /**
     * @return Subscription | null
     */
    private function upcomingSubscription(): ?Subscription
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->subscriptions()->upcoming()->first();
    }

    public function getUpcomingSubscriptionAttribute(): ?Subscription
    {
        return $this->upcomingSubscription();
    }

    /**
     * @return Subscription | null
     */
    private function latestSubscription(): ?Subscription
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->subscriptions()->latest('created_at')->first();
    }

    public function getLatestSubscriptionAttribute(): ?Subscription
    {
        return $this->latestSubscription();
    }

    private function activeOrLastSubscription()
    {
        if ($this->hasActiveSubscription()) {
            return $this->activeSubscription();
        }

        return $this->subscriptions()->latest('starts_at')->first();
    }

    public function getActiveOrLastSubscriptionAttribute(): ?Subscription
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->activeOrLastSubscription();
    }

    /**
     * Check if the model has an active subscription right now.
     *
     * @return bool Whether the bound model has an active subscription or not.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() ? true : false;
    }

    public function hasUnpaidSubscriptions(): bool
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return (bool)$this->subscriptions()->unpaid()->first();
    }

    public function hasUpcomingSubscription(): bool
    {
        return $this->upcomingSubscription() ? true : false;
    }

    /**
     * @param Plan $plan
     * @param bool $isRecurring
     * @param int $testingDays
     * @param int $duration
     * @param mixed | null $startsAt
     * @return Subscription
     * @throws SubscriptionException
     */
    public function subscribeTo(Plan $plan,
                                bool $isRecurring = true,
                                int $testingDays = 0,
                                int $duration = 30,
                                $startsAt = null): Subscription
    {
        $subscriptionModel = config('subscriptions.models.subscription');

        /** @var AbstractPlanTypeDateProcessor $dateProcessor */
        $dateProcessor = app()->makeWith($plan->plan_type_date_processor, [
            'testingDays' => $testingDays,
            'startAt' => $startsAt,
            'duration' => $duration
        ]);

        if ($this->hasActiveSubscription() && $this->activeSubscription()->expires_at->gt(Carbon::parse($startsAt))) {
            throw new SubscriptionException('The user has a conflicting active subscription');
        }

        if ($this->hasUpcomingSubscription()) {
            if ($this->upcomingSubscription()->expires_at->gt($dateProcessor->getStartDate()) ||
            $dateProcessor->getStartDate()->gt($this->upcomingSubscription()->starts_at)) {
                throw new SubscriptionException('The user has a conflicting future subscription');
            }
        }

        try {
            /** @var Subscription $subscription */
            $subscription = $this->subscriptions()->save(new $subscriptionModel([
                'plan_id' => $plan->id,
                'test_ends_at' => $dateProcessor->getTestEndsDate(),
                'starts_at' => $dateProcessor->getStartDate(),
                'expires_at' => $dateProcessor->getExpirationDate(),
                'cancelled_at' => null,
                'paid_at' => $plan->price === 0 ? Carbon::now() : null,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'is_recurring' => $isRecurring,
            ]));
            if (false === $subscription || null === $subscription) {
                throw new SubscriptionException('could not attach subscription');
            }
        } catch (Exception $ex) {
            throw new SubscriptionException('The subscription could not be saved');
        }

        $this->dispatchSubscriptionEvent(new NewSubscription( $subscription));

        return $subscription;
    }

    /**
     * @param Plan $plan
     * @param bool $isRecurring
     * @param bool $immediate
     * @param int $duration
     * @return Subscription
     * @throws SubscriptionException
     */
    public function migrateSubscriptionTo(Plan $plan,
                              bool $isRecurring = true,
                              bool $immediate = false,
                              int $duration = 30): Subscription
    {
        /** @var Subscription $previousSubscription */
        if (!$previousSubscription = $this->activeSubscription()) {
            throw new SubscriptionException('no active subscription found');
        }

        if (false === $immediate && $previousSubscription->is_testing) {
            throw new SubscriptionException('can only migrate a subscription in the test phase immediately');
        }

        try {
            /** @var Subscription $newSubscription */
            $previousSubscription->cancel($immediate);

            $this->muteSubscriptionEventDispatcher();

            $newSubscription = $this->subscribeTo($plan,
                $isRecurring,
                0,
                $duration,
                $immediate ? Carbon::now() : $previousSubscription->expires_at);

            $this->resumeSubscriptionEventDispatcher();
            $this->dispatchSubscriptionEvent(new SubscriptionMigrated($previousSubscription->fresh(), $newSubscription));
            return $newSubscription;
        } catch (Exception $e) {
            throw new SubscriptionException('could not migrate the subscription to a new plan');
        }
    }

    /**
     * @param int $days
     * @return Subscription
     * @throws SubscriptionException
     */
    public function extendSubscription(int $days): Subscription
    {
        /** @var Subscription $activeSubscription */
        if (!$activeSubscription  = $this->activeSubscription()) {
            throw new SubscriptionException('no active subscription found');
        }
        $activeSubscription->update([
            'expires_at' => Carbon::parse($activeSubscription->expires_at)->addDays($days)->endOfDay(),
        ]);

        $this->dispatchSubscriptionEvent(new SubscriptionExtended($activeSubscription));

        return $activeSubscription;
    }

    /**
     * @param $date
     * @return Subscription
     * @throws SubscriptionException
     */
    public function extendSubscriptionTo($date): Subscription
    {
        /** @var Subscription $activeSubscription */
        if (!$activeSubscription  = $this->activeSubscription()) {
            throw new SubscriptionException('no active subscription found');
        }

        $activeSubscription->update([
            'expires_at' => Carbon::parse($date)->endOfDay(),
        ]);

        $this->dispatchSubscriptionEvent(new SubscriptionExtended($activeSubscription));
        return $activeSubscription;
    }


    /**
     * @param bool $markAsPaid
     * @return Subscription
     * @throws SubscriptionException
     */
    public function renewExpiredSubscription(bool $markAsPaid = false): Subscription
    {
        if ($this->activeSubscription()) {
            throw new SubscriptionException('active subscription found');
        }

        /** @var Subscription $latestSubscription */
        if (!$latestSubscription = $this->activeOrLastSubscription()) {
            throw new SubscriptionException('no subscriptions found');
        }

        $this->muteSubscriptionEventDispatcher();
        $renewedSubscription = $this->subscribeTo(
            $latestSubscription->plan,
            $latestSubscription->is_recurring,
            0,
            Carbon::parse($latestSubscription->starts_at)->diffInDays($latestSubscription->expires_at));

        if ($markAsPaid) {
            $renewedSubscription->update([
                'paid_at' => Carbon::now()
            ]);
        }
        $this->resumeSubscriptionEventDispatcher();

        $renewedSubscription->update([
            'renewed_at' => Carbon::now()
        ]);

        $this->dispatchSubscriptionEvent(new SubscriptionRenewed($renewedSubscription));

        return $renewedSubscription;
    }

    /**
     * @param bool $immediate
     * @return Subscription
     * @throws SubscriptionException
     */
    public function cancelSubscription(bool $immediate = false): Subscription
    {
        /** @var Subscription $subscription */
        if (!$this->hasActiveSubscription()) {
            throw new SubscriptionException('No active subscription found');
        }

        $subscription = $this->activeSubscription();
        $subscription->cancel($immediate);

        $this->dispatchSubscriptionEvent(new SubscriptionCancelled($subscription));

        return $subscription;
    }


    /**
     * @param bool $markAsPaid
     * @return Subscription
     * @throws SubscriptionException
     */
    public function renewExpiringSubscription(bool $markAsPaid = false): Subscription
    {
        /** @var Subscription $activeSubscription */
        if (!$activeSubscription = $this->activeSubscription()) {
            throw new SubscriptionException('No active subscription found');
        }

        if (!$activeSubscription->is_paid) {
            throw new SubscriptionException('Renewing is not possible if currently active Subscription is not paid');
        }

        if (!$activeSubscription->is_expiring) {
            throw new SubscriptionException('Renewing is not possible if subscription is expiring earlyer than tomorrow midnight');
        }

        if (!$activeSubscription->is_recurring) {
            throw new SubscriptionException('Renewing a non-recurring subscription is not possible');
        }

        if ($activeSubscription->is_pending_cancellation) {
            throw new SubscriptionException('Renewing a subscription that is pending cancellation is not possible');
        }

        $this->muteSubscriptionEventDispatcher();

        /** @var Subscription $renewedSubscription */
        $renewedSubscription = $this->subscribeTo(
            $activeSubscription->plan,
            true,
            0,
            Carbon::parse($activeSubscription->starts_at)
                ->diffInDays($activeSubscription->expires_at),
            Carbon::parse('+ 2 days')->startOfDay());

        if ($markAsPaid) {
            $renewedSubscription->update([
                'paid_at' => Carbon::now()
            ]);
        }
        $renewedSubscription->update([
            'renewed_at' => Carbon::now()
        ]);

        $this->resumeSubscriptionEventDispatcher();

        $this->dispatchSubscriptionEvent(new SubscriptionRenewed( $renewedSubscription));
        return $renewedSubscription;
    }
}
