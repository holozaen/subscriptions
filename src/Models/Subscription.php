<?php


namespace OnlineVerkaufen\Plan\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use OnlineVerkaufen\Plan\Events\FeatureConsumed;
use OnlineVerkaufen\Plan\Events\FeatureUnconsumed;
use OnlineVerkaufen\Plan\Events\SubscriptionCancelled;
use OnlineVerkaufen\Plan\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Plan\Exception\FeatureException;
use OnlineVerkaufen\Plan\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Plan\Exception\SubscriptionException;
use OnlineVerkaufen\Plan\Models\Feature\Usage;

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
 * @property mixed test_ends_at
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
        'refunded_at',
        'test_ends_at'
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

    public function features(): HasMany
    {
        return $this->plan->features();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(config('plan.models.usage'), 'subscription_id');
    }

    public function scopeActive($query): Builder //regular or testing
    {
        return $query->where(
            static function($query) {
                return $query->where(Carbon::now(), '>=', DB::raw('starts_at')) // is started
                ->whereNotNull('paid_at') // is paid
                ->where(Carbon::now(),'<=',  DB::raw('expires_at')) // has not expired
                ->where(static function($query) {  // is not cancelled
                    return $query->whereNull('cancelled_at')
                        ->orWhere(DB::raw('cancelled_at'), '>', Carbon::now()); // not cancelled
                })
                    ->whereNull('refunded_at'); // is not refunded
            }
        )
            ->orWhere(static function ($query) {
                return $query->whereNotNull('test_ends_at')->where(Carbon::now(),'<', DB::raw('test_ends_at'));
            });
    }

    public function scopeRegular($query): Builder
    {
        return $query->where(Carbon::now(), '>=', DB::raw('starts_at')) // is started
                    ->whereNotNull('paid_at') // is paid
                    ->where(Carbon::now(),'<=',  DB::raw('expires_at')) // has not expired
                    ->where(static function($query) {  // is not cancelled
                        return $query->whereNull('cancelled_at')
                            ->orWhere(DB::raw('cancelled_at'), '>', Carbon::now()); // not cancelled
                    })
                    ->whereNull('refunded_at'); // is not refunded
    }

    public function scopeTesting($query): Builder
    {
        return $query->whereNotNull('test_ends_at')->where(Carbon::now(),'<', DB::raw('test_ends_at'));
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
        return (null !== $this->test_ends_at && Carbon::now()->lessThan(Carbon::parse($this->test_ends_at)));
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
        return ($this->cancelled_at !== null && $this->cancelled_at >= Carbon::now());
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

        return $this;
    }

    public function cancel($immediate = false): self
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

    public function consumeFeature(string $featureCode, int $amount): void
    {
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
        event(new FeatureConsumed($this, $this->getFeatureByCode($featureCode), $amount, $this->getRemainingOf($featureCode)));
    }

    public function unconsumeFeature(string $featureCode, int $amount): void
    {
        $usage = $this->usageModelOf($featureCode);

        $usage->decreaseBy($amount);

        event(new FeatureUnconsumed($this, $this->getFeatureByCode($featureCode), $amount, $this->getRemainingOf($featureCode)));
    }


    /**
     * @param string $featureCode
     * @return int
     * @throws FeatureNotFoundException
     */
    public function getUsageOf(string $featureCode): int
    {
        /** @var Usage $usage */
        $usage = $this->usages()->code($featureCode)->first();

        /** @var Feature $feature */
        $feature = $this->getFeatureByCode($featureCode);

        return $usage->used;
    }

    /**
     * Get the amount remaining for a feature.
     *
     * @param string $featureCode The feature code. This feature has to be 'limit' type.
     * @return int The amount remaining.
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function getRemainingOf(string $featureCode): int
    {
        $usage = $this->usageModelOf($featureCode);

        /** @var Feature $feature */
        $feature = $this->getFeatureByCode($featureCode);

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
     * @throws FeatureNotFoundException
     */
    private function usageModelOf(string $featureCode): Usage
    {
        $usage = $this->usages()->code($featureCode)->first();

        if (!$usage) {
            /** @var Feature $feature */
            $feature = $this->getFeatureByCode($featureCode);

            if ($feature->isFeatureType()) {
                throw new FeatureException('This feature is not limited and thus can not be consumed');
            }
            $usage = $this->createEmptyUsage($featureCode);
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
        $feature = $this->features()->code($featureCode)->first();
        if (!is_a ($feature, Feature::class)) {
            throw new FeatureNotFoundException(sprintf('No feature found with code %s', $featureCode));
        }
        return $feature;
    }

    /**
     * @param string $featureCode
     * @param int $amount
     * @return bool
     * @throws FeatureException
     * @throws FeatureNotFoundException
     */
    public function hasAvailable(string $featureCode, int $amount): bool
    {
        return $this->getUsageOf($featureCode) + $amount < $this->getRemainingOf($featureCode);
    }
}
