<?php /** @noinspection PhpUnused */


namespace OnlineVerkaufen\Subscriptions\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OnlineVerkaufen\Subscriptions\Events\DispatchesSubscriptionEvents;
use OnlineVerkaufen\Subscriptions\Events\FeatureConsumed;
use OnlineVerkaufen\Subscriptions\Events\FeatureUnconsumed;
use OnlineVerkaufen\Subscriptions\Events\FeatureUsageReset;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Subscriptions\Exception\FeatureException;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;
use OnlineVerkaufen\Subscriptions\Models\Feature\Usage;

/**
 * @property int id
 * @property int plan_id
 * @property int model_id
 * @property string model_type
 * @property int price
 * @property string currency
 * @property bool is_recurring
 *
 * @property Model model
 * @property mixed paid_at
 * @property mixed payment_tolerance_ends_at
 * @property mixed test_ends_at
 * @property mixed starts_at
 * @property mixed expires_at
 * @property mixed cancelled_at
 * @property mixed refunded_at
 * @property mixed renewed_at
 * @property mixed created_at
 * @property array feature_usage_stats
 * @property array feature_authorizations
 * @property int remaining_days
 *
 * @property Plan plan
 * @property HasMany features
 * @property boolean has_started
 * @property boolean is_expiring
 * @property boolean is_testing
 * @property boolean is_paid
 * @property boolean is_pending_cancellation
 * @property boolean is_cancelled
 * @property boolean is_renewed
 * @property boolean is_active
 * @property boolean is_refunded
 * @property boolean is_within_payment_tolerance_time
 *
 * @method static Builder active
 * @method static Builder expiring
 * @method static Builder paid
 * @method static Builder recurring
 * @method static Builder regular
 * @method static Builder testing
 * @method static Builder unpaid
 * @method static Builder upcoming
 * @method static Builder withinPaymentTolerance
 */


class Subscription extends Model
{
    use DispatchesSubscriptionEvents;

    protected $table = 'plan_subscriptions';
    protected $guarded = [];
    protected $dates = [
        'paid_at',
        'payment_tolerance_ends_at',
        'starts_at',
        'expires_at',
        'renewed_at',
        'cancelled_at',
        'refunded_at',
        'test_ends_at'
    ];
    protected $casts = [
        'is_recurring' => 'boolean',
    ];

    protected $appends = ['is_active'];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.plan'), 'plan_id');
    }

    public function features(): HasMany
    {
        return $this->plan->features();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(config('subscriptions.models.usage'), 'subscription_id');
    }

    public function scopeActive($query): Builder //regular or testing
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where(
            static function($query) {
                /** @noinspection PhpUndefinedMethodInspection */
                return $query->where('starts_at','<=', Carbon::now()) // is started
                ->where(static function($query) {
                    /** @noinspection PhpUndefinedMethodInspection */
                    return $query->whereNotNull('paid_at')
                        ->orWhere('payment_tolerance_ends_at', '>', Carbon::now());
                }) // is paid or whithin payment tolerance
                ->where('expires_at' ,'>=',  Carbon::now()) // has not expired
                ->where(static function($query) {  // is not cancelled
                    /** @noinspection PhpUndefinedMethodInspection */
                    return $query->whereNull('cancelled_at')
                        ->orWhere('cancelled_at', '>', Carbon::now()); // not cancelled
                })
                    ->whereNull('refunded_at'); // is not refunded
            }
        )
            ->orWhere(static function ($query) {
                /** @noinspection PhpUndefinedMethodInspection */
                return $query->whereNotNull('test_ends_at')->where('test_ends_at','>', Carbon::now());
            });
    }

    public function scopeRegular($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('starts_at','<=', Carbon::now()) // is started
        ->where(static function($query) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $query->whereNotNull('paid_at')
                ->orWhere('payment_tolerance_ends_at', '>', Carbon::now());
        }) // is paid or whithin payment tolerance
        ->where('expires_at' ,'>=',  Carbon::now()) // has not expired
        ->where(static function($query) {  // is not cancelled
            /** @noinspection PhpUndefinedMethodInspection */
            return $query->whereNull('cancelled_at')
                ->orWhere('cancelled_at', '>', Carbon::now()); // not cancelled
        })
            ->whereNull('refunded_at'); // is not refunded
    }

    public function scopeTesting($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->whereNotNull('test_ends_at')->where('test_ends_at','>', Carbon::now());
    }

    public function scopePaid($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->whereNotNull('paid_at');
    }

    public function scopeRecurring($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('is_recurring', 1);
    }

    public function scopeUnpaid($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->whereNull('paid_at');
    }

    public function scopeExpiring($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('expires_at', '>', Carbon::tomorrow()->endOfDay()->subSecond())
            ->where('expires_at', '<=', Carbon::tomorrow()->endOfDay()->addSecond());
    }

    public function scopeUpcoming($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('starts_at', '>', Carbon::now());
    }

    public function scopeWithinPaymentTolerance($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('payment_tolerance_ends_at', '>', Carbon::now());
    }

    private function hasStarted(): bool
    {
        return Carbon::now()->greaterThanOrEqualTo(Carbon::parse($this->starts_at)->startOfDay());
    }

    public function getHasStartedAttribute(): bool
    {
        return $this->hasStarted();
    }

    private function isTesting(): bool
    {
        return (null !== $this->test_ends_at && Carbon::now()->lessThan(Carbon::parse($this->test_ends_at)));
    }

    public function getIsTestingAttribute(): bool
    {
        return $this->isTesting();
    }

    private function isUpcoming(): bool
    {
        return $this->starts_at > Carbon::now();
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->isUpcoming();
    }

    private function isWithinPaymentToleranceTime(): bool
    {
        return (Carbon::now()->lessThan(Carbon::parse($this->payment_tolerance_ends_at)));
    }

    public function getIsWithinPaymentToleranceTimeAttribute(): bool
    {
        return $this->isWithinPaymentToleranceTime();
    }

    private function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function getIsPaidAttribute(): bool
    {
       return $this->isPaid();
    }

    private function isCancelled(): bool
    {
        return ($this->cancelled_at !== null && $this->cancelled_at <= Carbon::now());
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->isCancelled();
    }

    private function isPendingCancellation(): bool
    {
        return ($this->cancelled_at !== null && $this->cancelled_at >= Carbon::now());
    }

    public function getIsPendingCancellationAttribute(): bool
    {
        return $this->isPendingCancellation();
    }

    private function isRefunded(): bool
    {
        return ($this->refunded_at !== null);
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->isRefunded();
    }

    private function isRenewed(): bool
    {
        return ($this->renewed_at !== null);
    }

    public function getIsRenewedAttribute(): bool
    {
        return $this->isRenewed();
    }

    private function isExpiring(): bool
    {
        return ($this->expires_at > Carbon::tomorrow()->endOfDay()->subSecond() &&
            $this->expires_at < Carbon::tomorrow()->endOfDay()->addSecond());
    }

    public function getIsExpiringAttribute(): bool
    {
        return $this->isExpiring();
    }

    private function hasExpired(): bool
    {
        return Carbon::now()->greaterThan(Carbon::parse($this->expires_at));
    }

    public function getHasExpiredAttribute(): bool
    {
        return $this->hasExpired();
    }

    private function isActive(): bool
    {
        if ($this->isTesting()) {
            return true;
        }

        return ($this->hasStarted() &&
            ($this->isPaid() || $this->isWithinPaymentToleranceTime()) &&
            !$this->hasExpired() &&
            !$this->isCancelled() &&
            !$this->isRefunded());
    }

    public function getIsActiveAttribute(): bool
    {
       return $this->isActive();
    }

    /**
     * @throws SubscriptionException
     */
    public function getRemainingDaysAttribute(): int
    {
        if (!$this->hasStarted()) {
            throw new SubscriptionException('Subscription not yet started');
        }

        return $this->hasExpired() ?
            0:
            (int) Carbon::now()->diffInDays(Carbon::parse($this->expires_at));
    }

    public function markAsPaid(): self
    {
        $this->update([
            'paid_at' => Carbon::now()
        ]);

        $this->dispatchSubscriptionEvent(new SubscriptionPaymentSucceeded($this));

        return $this;
    }

    /**
     * @param bool $immediate
     * @return Subscription
     * @throws SubscriptionException
     */
    public function cancel(bool $immediate = false): self
    {
        if ($this->isCancelled()) {
            throw new SubscriptionException('subscription is already cancelled or pending cancellation');
        }

        if ($this->isTesting()) {
            $this->update([
                'starts_at' => Carbon::now(),
                'test_ends_at' => Carbon::now()
            ]);
        }

        $this->update([
            'cancelled_at' => $immediate ? Carbon::now() : $this->expires_at,
            'is_recurring' => false
        ]);

        return $this;
    }

    /**
     * @param string $featureCode
     * @param int $amount
     * @param string|null $model_type
     * @param int|null $model_id
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function consumeFeature(string $featureCode, int $amount, ?string $model_type = null, ?int $model_id = null): void
    {
        /** @var Usage $usage */
        $usage = $this->usageModelOf($featureCode, $model_type, $model_id);

        if (!$this->hasAvailable($featureCode,$amount, $model_type, $model_id)) {
            throw new FeatureException(
                sprintf('Your usage exceeds the allowed usage amount. You tried to use %s, remaining were only %s',
                    $amount,
                    $this->getRemainingOf($featureCode, $model_type, $model_id)
                )
            );
        }

        $usage->increaseBy($amount);
        event(new FeatureConsumed($this, $this->getFeatureByCode($featureCode), $amount, $this->getRemainingOf($featureCode, $model_type, $model_id), $model_type, $model_id));
    }

    /**
     * @param string $featureCode
     * @param int $amount
     * @param string|null $model_type
     * @param int|null $model_id
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function unconsumeFeature(string $featureCode, int $amount, ?string $model_type = null, ?int $model_id = null): void
    {
        $usage = $this->usageModelOf($featureCode, $model_type, $model_id);

        $usage->decreaseBy($amount);

        event(new FeatureUnconsumed($this, $this->getFeatureByCode($featureCode), $amount, $this->getRemainingOf($featureCode, $model_type, $model_id), $model_type, $model_id));
    }

    /**
     * @param string $featureCode
     * @param string|null $model_type
     * @param int|null $model_id
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function resetFeatureUsage(string $featureCode, ?string $model_type = null, ?int $model_id = null): void
    {
        $usage = $this->usageModelOf($featureCode, $model_type, $model_id);

        $usage->reset();

        event(new FeatureUsageReset($this, $this->getFeatureByCode($featureCode), $model_type, $model_id));
    }


    /**
     * @param string $featureCode
     * @param string|null $model_type
     * @param int|null $model_id
     * @return int
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function getUsageOf(string $featureCode, ?string $model_type = null, ?int $model_id = null): int
    {
        $usage = $this->usageModelOf($featureCode,$model_type, $model_id);
        /** @var Usage $usage */
        /** @noinspection PhpUndefinedMethodInspection */
        return $usage->used;
    }

    /**
     * Get the amount remaining for a feature.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @param string|null $model_type
     * @param int|null $model_id
     * @return int The amount remaining.
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function getRemainingOf(string $featureCode, ?string $model_type = null, ?int $model_id = null): int
    {
        $usage = $this->usageModelOf($featureCode, $model_type, $model_id);

        /** @var Feature $feature */
        $feature = $this->getFeatureByCode($featureCode);

        if ($feature->isUnlimited()) {
            return 9999;
        }

        return (int) ($feature->isUnlimited()) ? 9999 : ($feature->limit - $usage->used);
    }

    /**
     * @param string $featureCode
     * @param string|null $model_type
     * @param int|null $model_id
     * @return Usage
     */
    private function createEmptyUsage(string $featureCode, ?string $model_type, ?int $model_id): Usage
    {
        $usageModel = config('subscriptions.models.usage');

        /** @var Usage $usage */
        $usage = $this->usages()->save(new $usageModel([
            'code' => $featureCode,
            'model_type' => $model_type,
            'model_id' => $model_id,
            'used' => 0,
        ]));

        return $usage;
    }

    /**
     * @param string $featureCode
     * @param string|null $model_type
     * @param int|null $model_id
     * @return Usage
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    private function usageModelOf(string $featureCode, ?string $model_type, ?int $model_id): Usage
    {
        if (!$model_type) {
            /** @noinspection PhpUndefinedMethodInspection */
            $usage = $this->usages()->code($featureCode)->first();
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            $usage = $this->usages()->code($featureCode)->where('model_type', $model_type)->where('model_id', $model_id)->first();
        }

        if (!$usage) {
            /** @var Feature $feature */
            $feature = $this->getFeatureByCode($featureCode);

            if ($feature->isFeatureType()) {
                throw new FeatureException('This feature is not limited and thus can not be consumed');
            }
            $usage = $this->createEmptyUsage($featureCode, $model_type, $model_id);
        }

        return $usage;
    }

    /**
     * @param string $featureCode
     * @return Feature
     * @throws FeatureNotFoundException
     */
    private function getFeatureByCode(string $featureCode): Feature
    {
        /** @var Feature $feature */
        /** @noinspection PhpUndefinedMethodInspection */
        $feature = $this->features()->code($featureCode)->first();
        if (!is_a ($feature, Feature::class)) {
            throw new FeatureNotFoundException(sprintf('No feature found with code %s', $featureCode));
        }
        return $feature;
    }

    /**
     * @param string $featureCode
     * @param int $amount
     * @param string|null $model_type
     * @param int|null $model_id
     * @return bool
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function hasAvailable(string $featureCode, int $amount, ?string $model_type, ?int $model_id): bool
    {
        return $amount <= $this->getRemainingOf($featureCode, $model_type, $model_id);
    }

    /**
     * @return array
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function getFeatureUsageStatsAttribute(): array
    {
        $usageStats = [];
        /** @var Feature\ $feature */
        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($this->features()->limited()->get() as $feature) {
            $usageStats[] = [
                'code' => $feature->code,
                'type' => 'limited',
                'usage' => $this->getUsageStatsOf($feature->code),
                'remaining' => $this->getRemainingStatsOf($feature->code),
            ];
        }

        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($this->features()->unlimited()->get() as $feature) {
            $usageStats[] = [
                'code' => $feature->code,
                'type' => 'unlimited',
                'usage' => $this->getUsageStatsOf($feature->code),
            ];
        }

        return $usageStats;
    }

    public function getFeatureAuthorizationsAttribute(): array
    {
        $authorizations = [];
        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($this->features()->feature()->get() as $feature) {
            $authorizations[] = $feature->code;
        }

        return $authorizations;
    }

    /**
     * @param $featureCode
     * @return array|int
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    private function getUsageStatsOf($featureCode)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $usages = Usage::code($featureCode)->get();
        if (count($usages) === 0) {
            return $this->getUsageOf($featureCode);
        }
        if (count($usages) === 1 && $usages[0]->model_type === null) {
            return $this->getUsageOf($featureCode);
        }
        $stats = [];
        foreach ($usages as $usage) {
            $stats[] = [
                'model_type' =>  $usage->model_type,
                'model_id' => $usage->model_id,
                'usage' => $this->getUsageOf($featureCode, $usage->model_type, $usage->model_id),
                'remaining' => $this->getRemainingOf($featureCode, $usage->model_type, $usage->model_id)
            ];
        }
        return $stats;
    }

    /**
     * @param $featureCode
     * @return int|null
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    private function getRemainingStatsOf($featureCode): ?int
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $usages = Usage::code($featureCode)->get();
        if (count($usages) === 0) {
            return $this->getRemainingOf($featureCode);
        }
        if (count($usages) === 1 && $usages[0]->model_type === null) {
            return $this->getRemainingOf($featureCode);
        }
        return null;
    }
}
