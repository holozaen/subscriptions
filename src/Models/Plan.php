<?php


namespace OnlineVerkaufen\Plan\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors\Duration;
use OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors\Monthly;
use OnlineVerkaufen\Plan\Models\PlanTypeDateProcessors\Yearly;

/**
 * @property int id
 * @property int position
 * @property string state
 * @property string name
 * @property string type
 * @property string description
 * @property int price
 * @property string currency
 * @property int duration
 *
 * @method static Builder active
 * @method static Builder disabled
 * @method static Builder visible
 */
class Plan extends Model
{
    public const TYPE_DURATION = Duration::class;
    public const TYPE_YEARLY = Yearly::class;
    public const TYPE_MONTHLY = Monthly::class;

    public const STATE_ACTIVE = 'active';
    public const STATE_ACTIVE_INVISIBLE = 'passive';
    public const STATE_DISABLED = 'inactive';

    protected $table = 'plans';
    protected $guarded = [];
    protected $casts = [
        'is_subscribable' => 'boolean',
    ];

    public function scopeActive($query): Builder
    {
        return $query->where('state', $this::STATE_ACTIVE)->orWhere('state', $this::STATE_ACTIVE_INVISIBLE);
    }

    public function scopeDisabled($query): Builder
    {
        return $query->where('state', $this::STATE_DISABLED);
    }

    public function scopeVisible($query): Builder
    {
        return $query->where('state', $this::STATE_ACTIVE);
    }

    public function features(): HasMany
    {
        return $this->hasMany(config('plan.models.feature'), 'plan_id')->orderBy('position', 'ASC');
    }

    public function subscriptions()
    {
        return $this->hasMany(config('plan.models.subscription'), 'plan_id');
    }
}
