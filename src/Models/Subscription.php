<?php


namespace OnlineVerkaufen\Plan\Models;


use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use OnlineVerkaufen\Plan\Events\FeatureConsumed;
use OnlineVerkaufen\Plan\Events\FeatureUnconsumed;
use OnlineVerkaufen\Plan\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Plan\Exception\FeatureException;
use OnlineVerkaufen\Plan\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Plan\Exception\PlanNotFoundException;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;
use OnlineVerkaufen\Plan\Models\Feature\Usage;

/**
 * @property int id
 * @property int plan_id
 * @property int model_id
 * @property string model_type
 * @property string | null payment_method
 * @property int price
 * @property string currency
 * @property bool is_recurring
 *
 * @property mixed paid_at
 * @property mixed starts_at
 * @property mixed expires_at
 * @property mixed cancelled_at
 * @property mixed refunded_at
 * @property mixed created_at
 *
 * @property Plan plan
 * @property int remaining_days
 *
 * @method static Builder active
 * @method static Builder paid
 * @method static Builder unpaid
 */


class Subscription extends Model
{
    protected $table = 'plan_subscriptions';
    protected $guarded = [];
    protected $dates = [
        'paid_at',
        'starts_at',
        'expires_at',
        'renewed_at',
        'cancelled_at',
        'refunded_at'
    ];
    protected $casts = [
        'is_recurring' => 'boolean',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('plan.models.plan'), 'plan_id');
    }

    /** @throws PlanNotFoundException */
    public function features(): HasMany
    {
        try {
            return $this->plan->features();
        } catch (Exception $e) {
            throw new PlanNotFoundException('Plan subscription needs to have a plan attached');
        }
    }

    public function usages(): HasMany
    {
        return $this->hasMany(config('plan.models.usage'), 'subscription_id');
    }

    public function scopeActive($query): Builder
    {
        return $query->where(function($query) {
            return $query->where(Carbon::now()->endOfDay(),'<', DB::raw('starts_at'));
        })
            ->orWhere(function($query) {
                return $query->where(Carbon::now()->endOfDay(), '>=', DB::raw('starts_at')) //started
                    ->whereNotNull('paid_at')
                    ->where(Carbon::now()->endOfDay(),'<=',  DB::raw('expires_at')) //not expired
                    ->where(static function($query) {
                        return $query->whereNull('cancelled_at')
                            ->orWhere(DB::raw('cancelled_at'), '>', Carbon::now()->endOfDay()); // not cancelled
                    })
                    ->whereNull('refunded_at'); // not refunded
        });
    }

    public function scopePaid($query): Builder
    {
        return $query->whereNotNull('paid_at');
    }

    public function scopeUnpaid($query): Builder
    {
        return $query->whereNull('paid_at');
    }

    public function hasStarted(): bool
    {
        return Carbon::now()->greaterThanOrEqualTo(Carbon::parse($this->starts_at)->startOfDay());
    }

    public function isTesting(): bool
    {
        return Carbon::now()->endOfDay()->lessThan(Carbon::parse($this->starts_at));
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isCancelled(): bool
    {
        return ($this->cancelled_at !== null && $this->cancelled_at <= Carbon::now());
    }

    public function isPendingCancellation(): bool
    {
        return ($this->cancelled_at !== null && $this->cancelled_at >= Carbon::now()->startOfDay());
    }

    public function isRefunded(): bool
    {
        return ($this->refunded_at !== null);
    }

    public function hasExpired(): bool
    {
        return Carbon::now()->greaterThan(Carbon::parse($this->expires_at));
    }

    public function isActive(): bool
    {
        if ($this->isTesting()) {
            return true;
        }
        return ($this->hasStarted() &&
            $this->isPaid() &&
            !$this->hasExpired() &&
            !$this->isCancelled() &&
            !$this->isRefunded());
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

        event(new SubscriptionPaymentSucceeded( $this));
        return $this;
    }

    public function cancel($immediate = false): self
    {
        $this->update([
            'cancelled_at' => $immediate ? Carbon::now() : $this->expires_at,
            'is_recurring' => false
        ]);

        return $this;
    }

    public function consumeFeature(string $featureCode, float $amount): void
    {
        /** @var Feature $feature */
        if (!$feature = $this->features()->code($featureCode)->first()) {
            throw new FeatureNotFoundException(sprintf('No feature found with code %s', $featureCode));
        }

        /** @var Usage $usage */
        $usage = $this->usageModelOf($featureCode);

        if (!$this->hasAvailable($featureCode,$amount)) {
            throw new FeatureException(
                sprintf('Your usage exceeds the allowed usage amount. You tried to use %s, remaining were only %s',
                    $amount,
                    $this->getRemainingOf($featureCode)
                )
            );
        }

        $usage->increaseBy($amount);
        event(new FeatureConsumed($this, $feature, $amount, $this->getRemainingOf($featureCode)));
    }

    public function unconsumeFeature(string $featureCode, float $amount): void
    {
        /** @var Feature $feature */
        if (!$feature = $this->features()->code($featureCode)->first()) {
            throw new FeatureException(sprintf('No feature found with code %s', $featureCode));
        }

        /** @var Usage $usage */
        $usage = $this->usageModelOf($featureCode);

        $usage->decreaseBy($amount);

        event(new FeatureUnconsumed($this, $feature, $amount, $this->getRemainingOf($featureCode)));
    }

    /**
     * Get the amount used for a limit.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @return float
     * @throws FeatureException
     * @throws PlanNotFoundException
     */
    public function getUsageOf(string $featureCode): float
    {
        /** @var Usage $usage */
        $usage = $this->usages()->code($featureCode)->first();

        /** @var Feature $feature */
        $feature = $this->features()->code($featureCode)->first();

        if ($feature->isFeatureType()) {
            throw new FeatureException('This feature is not limited and thus can not be consumed');
        }

        return (float) $usage->used;
    }

    /**
     * Get the amount remaining for a feature.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @return int The amount remaining.
     * @throws FeatureException
     * @throws PlanNotFoundException
     */
    public function getRemainingOf(string $featureCode): int
    {
        $usage = $this->usageModelOf($featureCode);

        /** @var Feature $feature */
        $feature = $this->features()->code($featureCode)->first();

        if ($feature->isFeatureType()) {
            throw new FeatureException('This feature is not limited and thus can not be consumed');
        }

        if ($feature->isUnlimited()) {
            return 9999;
        }

        return (int) ($feature->isUnlimited()) ? 9999 : ($feature->limit - $usage->used);
    }

    /**
     * @param string $featureCode
     * @return Usage
     * @throws FeatureException
     */
    private function createEmptyUsage(string $featureCode): Usage
    {
        $usageModel = config('plan.models.usage');

        /** @var Usage $usage */
        $usage = $this->usages()->save(new $usageModel([
            'code' => $featureCode,
            'used' => 0,
        ]));

        if ($usage === false) {
            throw new FeatureException(sprintf('usage for feature with code %s has not been created', $featureCode));
        }

        return $usage;
    }

    /**
     * @param string $featureCode
     * @return Usage
     * @throws FeatureException
     * @throws PlanNotFoundException
     */
    private function usageModelOf(string $featureCode): Usage
    {
        $usage = $this->usages()->code($featureCode)->first();

        if (!$usage) {
            /** @var Feature $feature */
            $feature = $this->features()->code($featureCode)->first();

            if ($feature->isFeatureType()) {
                throw new FeatureException('This feature is not limited and thus can not be consumed');
            }
            $usage = $this->createEmptyUsage($featureCode);
        }

        return $usage;
    }

    /**
     * @param string $featureCode
     * @return mixed
     * @throws PlanNotFoundException
     */
    private function getLimitedFeatureByCode(string $featureCode)
    {
        return $this->features()->limited()->code($featureCode)->first();
    }
    /**
     * @param string $featureCode
     * @return mixed
     * @throws PlanNotFoundException
     */
    private function getUnimitedFeatureByCode(string $featureCode)
    {
        return $this->features()->unlimited()->code($featureCode)->first();
    }

    /**
     * @param string $featureCode
     * @param float $amount
     * @return bool
     * @throws FeatureException
     * @throws PlanNotFoundException
     */
    public function hasAvailable(string $featureCode, float $amount): bool
    {
        return $this->getUsageOf($featureCode) + $amount < $this->getRemainingOf($featureCode);
    }
}
