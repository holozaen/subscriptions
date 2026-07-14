<?php


namespace OnlineVerkaufen\Subscriptions\Test\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OnlineVerkaufen\Subscriptions\Test\Database\Factories\ImageFactory;

class Image extends Model
{
    use HasFactory;

    protected static function newFactory(): ImageFactory
    {
        return ImageFactory::new();
    }
}
