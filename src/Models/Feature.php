<?php /** @noinspection PhpUnused */


namespace OnlineVerkaufen\Subscriptions\Models;


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
 * @property string restricted_model
 * @property string restricted_relation
 *
 * @method static Builder code
 * @method static Builder feature
 * @method static Builder limited
 * @method static Builder unlimited
 * @method static create(array $array)
 */
class Feature extends Model
{
    public const TYPE_FEATURE = 'feature';
    public const TYPE_LIMIT = 'limit';

    protected $table = 'plan_features';
    protected $guarded = [];
    protected $casts = [
      'limit' => 'Integer'
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.models.plan'), 'plan_id');
    }

    public function scopeCode($query, string $code): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('code', $code);
    }

    public function scopeLimited($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('type', self::TYPE_LIMIT)->where('limit', '>', 0);
    }

    public function scopeUnlimited($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('type', self::TYPE_LIMIT)->where('limit', '=', 0);
    }

    public function scopeFeature($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('type', self::TYPE_FEATURE);
    }

    public function scopeLimit($query): Builder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $query->where('type', self::TYPE_LIMIT);
    }

    public function isLimitType(): bool
    {
        return ($this->type === self::TYPE_LIMIT);
    }

    public function isFeatureType(): bool
    {
        return ($this->type === self::TYPE_FEATURE);
    }

    public function isLimited(): bool
    {
        return ($this->type === self::TYPE_LIMIT && $this->limit > 0);
    }

    public function isUnlimited(): bool
    {
        return ($this->type === self::TYPE_LIMIT && $this->limit === 0);
    }
}
