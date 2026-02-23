<?php

namespace AbeTwoThree\LaravelTsPublisher\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AbeTwoThree\LaravelTsPublisher\LaravelTsPublisher
 */
class LaravelTsPublisher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AbeTwoThree\LaravelTsPublisher\LaravelTsPublisher::class;
    }
}
