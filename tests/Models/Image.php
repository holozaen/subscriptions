<?php


namespace OnlineVerkaufen\Subscriptions\Test\Models;


use Illuminate\Database\Eloquent\Model;
use OnlineVerkaufen\Subscriptions\Test\Database\Factories\ImageFactory;

class Image extends Model
{
    protected static function newFactory(): ImageFactory
    {
        return ImageFactory::new();
    }
}
