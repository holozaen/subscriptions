<?php /** @noinspection PhpUnused */


namespace OnlineVerkaufen\Subscriptions\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OnlineVerkaufen\Subscriptions\Events\DispatchesSubscriptionEvents;
use OnlineVerkaufen\Subscriptions\Events\SubscriptionPaymentSucceeded;
use OnlineVerkaufen\Subscriptions\Exception\FeatureNotFoundException;
use OnlineVerkaufen\Subscriptions\Exception\SubscriptionException;

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
     * @return Feature
     * @throws FeatureNotFoundException
     */
    public function getFeatureByCode(string $featureCode): Feature
    {
        /** @var Feature $feature */
        /** @noinspection PhpUndefinedMethodInspection */
        $feature = $this->features()->code($featureCode)->first();
        if (!is_a ($feature, Feature::class)) {
            throw new FeatureNotFoundException(sprintf('No feature found with code %s', $featureCode));
        }
        return $feature;
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

    public function getLimitsAttribute(): array
    {
        $limits = [];
        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($this->features()->limitType()->get() as $feature) {
            $limits[$feature->code] = $feature->limit;
        }

        return $limits;
    }

    public function getLimitForClassRelation(string $base_class_name, string $relation): ?int
    {
        $feature = $this->plan->features()
            ->where('type', Feature::TYPE_LIMIT)
            ->where('restricted_model', $base_class_name)
            ->where('restricted_relation', $relation)
            ->first();
        if (!$feature) {
            return null;
        }
        return $feature->limit;
    }
}
