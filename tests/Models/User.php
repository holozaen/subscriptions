<?php

namespace OnlineVerkaufen\Subscriptions\Test\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use OnlineVerkaufen\Subscriptions\Contracts\RelationsLimitable;
use OnlineVerkaufen\Subscriptions\Models\HasSubscriptions;
use OnlineVerkaufen\Subscriptions\Models\HasLimitedRelations;
use OnlineVerkaufen\Subscriptions\Models\Subscription;

/**
 * @property mixed id
 * @property Subscription|null active_subscription
 */
class User extends Authenticatable implements RelationsLimitable
{
    use HasSubscriptions, HasLimitedRelations;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    /** @noinspection PhpUnused */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function getActiveSubscription(): ?Subscription
    {
        return $this->active_subscription;
    }
}
