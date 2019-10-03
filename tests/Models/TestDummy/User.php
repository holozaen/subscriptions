<?php

namespace OnlineVerkaufen\Subscriptions\Test\Models\TestDummy;

use Illuminate\Foundation\Auth\User as Authenticatable;
use OnlineVerkaufen\Subscriptions\Models\HasPlans;

class User extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function renewExpiringSubscription(): bool
    {
        return true;
    }
}
