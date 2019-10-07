<?php


namespace OnlineVerkaufen\Subscriptions\Models\Feature;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OnlineVerkaufen\Subscriptions\Models\Feature;
use OnlineVerkaufen\Subscriptions\Models\Subscription;

/**
 * @property int id
 * @property int subscription_id
 * @property int used
 *
 * @property Feature feature
 * @property Subscription subscription
 * @method static first()
 */

class Usage extends Model
{
    protected $table = 'plan_feature_usages';
    protected $guarded = [];
    protected $casts = [
      'used' => 'Integer'
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.subscription'), 'subscription_id');
    }

    /** @noinspection PhpUnused */
    public function scopeCode($query, string $code): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('code', $code);
    }

    public function increaseBy(int $amount): void
    {
        $this->update(
            [
                'used' => $this->used + $amount
            ]);
    }

    public function decreaseBy(int $amount): void
    {
        $this->update(
            [
                'used' => ($this->used - $amount) > 0 ? ($this->used - $amount) : 0
            ]);
    }
}
