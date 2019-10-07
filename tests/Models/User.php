<?php

namespace OnlineVerkaufen\Subscriptions\Test\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use OnlineVerkaufen\Subscriptions\Models\HasPlans;

/**
 * @property mixed id
 */
class User extends Authenticatable
{
    use HasPlans;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];
}
