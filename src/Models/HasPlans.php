<?php

namespace OnlineVerkaufen\Plan\Models;

use Carbon\Carbon;
use Exception;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use OnlineVerkaufen\Plan\Events\DispatchesSubscriptionEvents;
use OnlineVerkaufen\Plan\Events\NewSubscription;
use OnlineVerkaufen\Plan\Events\RenewedSubscription;
use OnlineVerkaufen\Plan\Events\SubscriptionCancelled;
use OnlineVerkaufen\Plan\Events\SubscriptionExtended;
use OnlineVerkaufen\Plan\Events\SubscriptionMigrated;
use OnlineVerkaufen\Plan\Exception\PlanException;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;
use OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors\AbstractPlanTypeDateProcessor;

trait HasPlans
{
    use DispatchesSubscriptionEvents;

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(config('plan.models.subscription'), 'model');
    }

    /**
     * @return Subscription | null
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->active()->first();
    }

    /**
     * @return Subscription | null
     */
    public function latestSubscription(): ?Subscription
    {
        return $this->subscriptions()->latest('created_at')->first();
    }

    /**
     * @return Subscription | null
     */
    public function regularSubscription(): ?Subscription
    {
        return $this->subscriptions()->regular()->first();
    }

    /**
     * @return Subscription | null
     */
    public function testingSubscription(): ?Subscription
    {
        return $this->subscriptions()->testing()->first();
    }

    /**
     * @throws MorphMany | Subscription | null
     */
    public function activeOrLastSubscription()
    {
        if (!$this->hasSubscriptions()) {
            return null;
        }

        if ($this->hasActiveSubscription()) {
            return $this->activeSubscription();
        }

        return $this->subscriptions()->latest('starts_at')->first();
    }

    /**
     * Check if the model has subscriptions.
     *
     * @return bool Whether the bound model has subscriptions or not.
     */
    public function hasSubscriptions(): bool
    {
        return ($this->subscriptions()->count() > 0);
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
        return (bool)$this->subscriptions()->paid();
    }


    /**
     * @param Plan $plan
     * @param bool $isRecurring
     * @param bool $isRenewal
     * @param int $testingDays
     * @param int $duration
     * @param mixed | null $startsAt
     * @return Subscription
     * @throws PlanException
     * @throws SubscriptionException
     */
    public function subscribeTo(Plan $plan,
                                bool $isRecurring = true,
                                bool $isRenewal = false,
                                int $testingDays = 0,
                                int $duration = 30,
                                $startsAt = null): Subscription
    {
        $subscriptionModel = config('plan.models.subscription');

        if ($duration < 1) {
            throw new PlanException('A plan has to have a duration that is greater than');
        }

        if ($this->hasActiveSubscription() && $this->activeSubscription()->expires_at < $startsAt) {
            throw new SubscriptionException('The user is already subscribed');
        }

        try {
            /** @var AbstractPlanTypeDateProcessor $dateProcessor */
            $dateProcessor = app()->makeWith($plan->type, [
                'testingDays' => $testingDays,
                'startAt' => $startsAt,
                'duration' => $duration
            ]);
        } catch (Exception $e) {
            throw new SubscriptionException('Expiration date computing error');
        }

        try {
            /** @var Subscription $subscription */
            $subscription = $this->subscriptions()->save(new $subscriptionModel([
                'plan_id' => $plan->id,
                'test_ends_at' => $dateProcessor->getTestEndsDate(),
                'starts_at' => $dateProcessor->getStartDate(),
                'expires_at' => $dateProcessor->getExpirationDate(),
                'cancelled_at' => null,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'is_recurring' => $isRecurring,
                'renewed_at' => $isRenewal ? Carbon::now() : null
            ]));
            if (false === $subscription) {
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
     * @throws PlanException
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

        if (false === $immediate && $previousSubscription->isTesting()) {
            throw new SubscriptionException('can only immediately migrate a subscription in the test phase');
        }

        try {
            /** @var Subscription $newSubscription */
            $previousSubscription->cancel($immediate);

            $this->muteSubscriptionEventDispatcher();

            $newSubscription = $this->subscribeTo($plan,
                $isRecurring,
                false,
                0,
                $duration,
                $immediate ? Carbon::now() : $previousSubscription->expires_at);

            $this->resumeSubscriptionEventDispatcher();

            $this->dispatchSubscriptionEvent(new SubscriptionMigrated($previousSubscription->fresh(), $newSubscription));
            return $newSubscription;
        } catch (Exception $e) {
            throw new PlanException('could not migrate the plan');
        }
    }

    /**
     * @param int $days
     * @return Subscription
     * @throws PlanException
     */
    public function extendSubscription(int $days): Subscription
    {
        /** @var Subscription $activeSubscription */
        if (!$activeSubscription  = $this->activeSubscription()) {
            throw new PlanException('no active subscription found');
        }

        try {
            $activeSubscription->update([
                'expires_at' => Carbon::parse($activeSubscription->expires_at)->addDays($days)->endOfDay(),
            ]);

            $this->dispatchSubscriptionEvent(new SubscriptionExtended($activeSubscription));

            return $activeSubscription;
        } catch (Exception $e) {
            throw new PlanException('could not extend active subscription');
        }
    }

    /**
     * @param $date
     * @return Subscription
     * @throws PlanException
     */
    public function extendSubscriptionTo($date): Subscription
    {
        /** @var Subscription $activeSubscription */
        if (!$activeSubscription  = $this->activeSubscription()) {
            throw new PlanException('no active subscription found');
        }

        try {
            $activeSubscription->update([
                'expires_at' => Carbon::parse($date)->endOfDay(),
            ]);
        } catch (Exception $e) {
            throw new PlanException('could not extend active subscription');
        }

        $this->dispatchSubscriptionEvent(new SubscriptionExtended($activeSubscription));

        return $activeSubscription;
    }


    /**
     * @param bool $markAsPaid
     * @return Subscription
     * @throws PlanException
     */
    public function renewLatestSubscription(bool $markAsPaid = false): Subscription
    {
        if ($this->activeSubscription()) {
            throw new PlanException('active subscription found');
        }

        /** @var Subscription $latestSubscription */
        if (!$latestSubscription = $this->activeOrLastSubscription()) {
            throw new PlanException('no subscriptions found');
        }

        try {
            $this->muteSubscriptionEventDispatcher();
            $renewedSubscription = $this->subscribeTo(
                $latestSubscription->plan,
                $latestSubscription->is_recurring,
                true,
                0,
                Carbon::parse($latestSubscription->starts_at)->diffInDays($latestSubscription->expires_at));

            if ($markAsPaid) {
                $renewedSubscription->update([
                    'paid_at' => Carbon::now()
                ]);
            }
            $this->resumeSubscriptionEventDispatcher();

            $this->dispatchSubscriptionEvent(new RenewedSubscription($renewedSubscription));

        } catch (Exception $e) {
            throw new PlanException('could not renew latest subscription');
        }

        return $renewedSubscription;
    }

    /**
     * @param bool $immediate
     * @return Subscription
     * @throws PlanException
     * @throws SubscriptionException
     */
    public function cancelSubscription(bool $immediate = false): Subscription
    {
        /** @var Subscription $subscription */
        if (!$subscription = $this->hasActiveSubscription()) {
            throw new PlanException('No active subscription found');
        }

        $subscription->cancel($immediate);

        $this->dispatchSubscriptionEvent(new SubscriptionCancelled($subscription));

        return $subscription;
    }


    /**
     * @param bool $markAsPaid
     * @return Subscription
     * @throws PlanException
     */
    public function renewSubscription(bool $markAsPaid = false): Subscription
    {
        if (!$activeSubscription = $this->activeSubscription()) {
            throw new PlanException('No active subscription found');
        }

        if ($this->hasUnpaidSubscriptions()) {
            throw new PlanException('Renewing is not possible with unpaid older subscriptions');
        }

        if (!$activeSubscription->is_recurring) {
            throw new PlanException('Renewing a non-recurring subscription is not possible');
        }

        if ($activeSubscription->isCancelled()) {
            throw new PlanException('Renewing a cancelled subscription is not possible');
        }

        try {
            $this->muteSubscriptionEventDispatcher();

            /** @var Subscription $renewedSubscription */
            $renewedSubscription = $this->subscribeTo(
                $activeSubscription->plan,
                true,
                true,
                0,
                Carbon::parse($activeSubscription->starts_at)
                    ->diffInDays($activeSubscription->expires_at));

            if ($markAsPaid) {
                $renewedSubscription->update([
                    'paid_at' => Carbon::now()
                ]);
            }

            $this->resumeSubscriptionEventDispatcher();
        } catch (Exception $e) {
            throw new PlanException('could not renew subscription');
        }

        $this->dispatchSubscriptionEvent(new RenewedSubscription( $renewedSubscription));
        return $renewedSubscription;
    }
}
