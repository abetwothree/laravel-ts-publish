<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 */
class LaravelTsPublish extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AbeTwoThree\LaravelTsPublish\LaravelTsPublish::class;
    }
}
