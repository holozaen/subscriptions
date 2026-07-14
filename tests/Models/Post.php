<?php


namespace OnlineVerkaufen\Subscriptions\Test\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OnlineVerkaufen\Subscriptions\Contracts\RelationsLimitable;
use OnlineVerkaufen\Subscriptions\Models\HasLimitedRelations;
use OnlineVerkaufen\Subscriptions\Models\Subscription;
use OnlineVerkaufen\Subscriptions\Test\Database\Factories\PostFactory;

/**
 * @property User $user
 */

class Post extends Model implements RelationsLimitable
{
    use HasLimitedRelations;

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function getActiveSubscription(): ?Subscription
    {
        return $this->user->active_subscription;
    }
}
