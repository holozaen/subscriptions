<?php

namespace OnlineVerkaufen\Plan\Test\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use OnlineVerkaufen\Plan\Models\HasPlans;

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
