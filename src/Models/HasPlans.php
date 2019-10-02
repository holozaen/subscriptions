<?php

namespace OnlineVerkaufen\Plan\Models;

use Carbon\Carbon;
use Exception;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use OnlineVerkaufen\Plan\Events\NewSubscription;
use OnlineVerkaufen\Plan\Events\RenewedSubscription;
use OnlineVerkaufen\Plan\Events\SubscriptionCancelled;
use OnlineVerkaufen\Plan\Events\SubscriptionExtended;
use OnlineVerkaufen\Plan\Events\SubscriptionMigrated;
use OnlineVerkaufen\Plan\Exception\PlanException;
use OnlineVerkaufen\Plan\Models\PlanTypes\PlanType as PlanTypeInterface;

trait HasPlans
{

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
     * @return Subscription
     * @throws PlanException
     */
    public function subscribeTo(Plan $plan,
                                bool $isRecurring = true,
                                bool $isRenewal = false,
                                int $testingDays = 0,
                                int $duration = 30): Subscription
    {
        $subscriptionModel = config('plan.models.subscription');

        if ($duration < 1) {
            throw new PlanException('A plan has to have a duration that is greater than');
        }

        if ($this->hasActiveSubscription()) {
            throw new PlanException('The user is already subscribed');
        }

        try {
            /** @var PlanTypeInterface $type */
            $type = new $plan->type;
            $expirationDate = $type->getExpirationDate(['days' => $duration, 'testing_days' => $testingDays]);
        } catch (Exception $e) {
            throw new PlanException('Expiration date computing error');
        }

        try {
            /** @var Subscription $subscription */
            $subscription = $this->subscriptions()->save(new $subscriptionModel([
                'plan_id' => $plan->id,
                'starts_at' => Carbon::now()->addDays($testingDays)->endOfDay(),
                'expires_at' => $expirationDate,
                'cancelled_at' => null,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'is_recurring' => $isRecurring,
                'renewed_at' => $isRenewal ? Carbon::now() : null
            ]));
            if (false === $subscription) {
                throw new PlanException('could not attach subscription');
            }
        } catch (Exception $ex) {
            throw new PlanException('The subscription could not be saved');
        }

        if ($isRenewal) {
            event(new RenewedSubscription($this, $subscription));
        } else {
            event(new NewSubscription($this, $subscription));
        }
        return $subscription;
    }

    /**
     * @param Plan $plan
     * @param string $type
     * @param bool $isRecurring
     * @param bool $immediate
     * @param int $duration
     * @return Subscription
     * @throws PlanException
     */
    public function migrateTo(Plan $plan,
                              string $type,
                              bool $isRecurring = true,
                              bool $immediate = false,
                              int $duration = 30): Subscription
    {
        /** @var Subscription $previousSubscription */
        if ($previousSubscription = !$this->hasActiveSubscription()) {
            throw new PlanException('no active subscription found');
        }

        try {
            /** @noinspection NullPointerExceptionInspection */
            $previousSubscription->cancel($immediate);

            /** @var Subscription $newSubscription */
            $newSubscription = $this->subscribeTo($plan,
                $isRecurring,
                false,
                $immediate ? 0 : Carbon::now()->diffInDays($this->activeSubscription()->expires_at),
                $duration);

            event(new SubscriptionMigrated($this, $previousSubscription, $newSubscription));
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

            event(new SubscriptionExtended($this, $activeSubscription));

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

        event(new SubscriptionExtended($this, $activeSubscription));
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
        } catch (Exception $e) {
            throw new PlanException('could not renew latest subscription');
        }

        return $renewedSubscription;
    }

    /**
     * @param bool $immediate
     * @return Subscription
     * @throws PlanException
     */
    public function cancelSubscription(bool $immediate = false): Subscription
    {
        /** @var Subscription $subscription */
        if (!$subscription = $this->hasActiveSubscription()) {
            throw new PlanException('No active subscription found');
        }

        if ($subscription->isCancelled() || $subscription->isPendingCancellation()) {
            throw new PlanException('Can not cancel an already cancelled subscription');
        }

        try {
            $subscription->cancel($immediate);
        } catch (Exception $e) {
            throw new PlanException('Cancelling the subscription failed');
        }


        event(new SubscriptionCancelled($this, $subscription));
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
        } catch (Exception $e) {
            throw new PlanException('could not renew subscription');
        }

        event(new RenewedSubscription($this, $renewedSubscription));
        return $renewedSubscription;
    }
}
