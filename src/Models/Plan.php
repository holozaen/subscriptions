<?php


namespace OnlineVerkaufen\Subscriptions\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OnlineVerkaufen\Subscriptions\Exception\PlanException;
use OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors\Duration;
use OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors\Monthly;
use OnlineVerkaufen\Subscriptions\Models\PlanTypeDateProcessors\Yearly;

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
 */

class Plan extends Model
{

    public const PLAN_TYPES = [
        [
            'code' => 'yearly',
            'class' => Yearly::class
        ],
        [
            'code' => 'monthly',
            'class' => Monthly::class
        ],
        [
            'code' => 'duration',
            'class' => Duration::class
        ]
    ];

    public const STATE_ACTIVE = 'active';
    public const STATE_DISABLED = 'inactive';

    protected $table = 'plans';
    protected $guarded = [];
    protected $casts = [
        'is_subscribable' => 'boolean',
    ];

    public function scopeActive($query): Builder
    {
        return $query->where('state', $this::STATE_ACTIVE);
    }

    public function scopeDisabled($query): Builder
    {
        return $query->where('state', $this::STATE_DISABLED);
    }

    public function features(): HasMany
    {
        return $this->hasMany(config('subscriptions.models.feature'), 'plan_id')->orderBy('position', 'ASC');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(config('subscriptions.models.subscription'), 'plan_id');
    }

    public function getPlanTypeDefinition(): array
    {
        $typeCode = $this->type;
        $definitionArray = array_filter($this::PLAN_TYPES, static function ($type)  use ($typeCode) { return $type['code'] === $typeCode; });
        if (is_array($definitionArray)) {
            return array_shift($definitionArray);
        }
        return null;
    }

    public function getPlanTypeDateProcessorClass(): string
    {
        $typeDefinition = $this->getPlanTypeDefinition();
        if (is_array($typeDefinition) && array_key_exists('class', $typeDefinition)) {
            return $this->getPlanTypeDefinition()['class'];
        }
        return null;
    }
}
