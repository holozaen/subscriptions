<?php


namespace OnlineVerkaufen\Plan\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id
 * @property int plan_id
 * @property string code
 * @property string type
 * @property int limit
 * @property int position
 * @property string name
 * @property string description
 *
 * @method static Builder code
 * @method static Builder feature
 * @method static Builder limited
 * @method static Builder unlimited
 */
class Feature extends Model
{
    protected $table = 'plan_features';
    protected $guarded = [];
    protected $casts = [
      'limit' => 'Integer'
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('plan.models.plan'), 'plan_id');
    }

    public function scopeCode($query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeLimited($query): Builder
    {
        return $query->where('type', 'limit')->where('limit', '>', 0);
    }

    public function scopeUnlimited($query): Builder
    {
        return $query->where('type', 'limit')->where('limit', '=', 0);
    }

    public function scopeFeature($query): Builder
    {
        return $query->where('type', 'feature');
    }

    public function isLimitType(): bool
    {
        return ($this->type === 'limit');
    }

    public function isFeatureType(): bool
    {
        return ($this->type === 'feature');
    }

    public function isLimited(): bool
    {
        return ($this->type === 'limit' && $this->limit > 0);
    }

    public function isUnlimited(): bool
    {
        return ($this->type === 'limit' && $this->limit === 0);
    }
}
