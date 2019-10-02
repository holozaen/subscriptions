<?php


namespace OnlineVerkaufen\Plan\Models\Feature;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OnlineVerkaufen\Plan\Models\Feature;
use OnlineVerkaufen\Plan\Models\Subscription;

/**
 * @property int id
 * @property int subscription_id
 * @property int used
 *
 * @property Feature feature
 * @property Subscription subscription
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
        return $this->belongsTo(config('plan.models.subscription'), 'subscription_id');
    }

    public function scopeCode($query, string $code): Builder
    {
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
