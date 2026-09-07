<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Facades;

use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use Illuminate\Support\Facades\Facade;

/**
 * @see LaravelTsPublishService
 */
class LaravelTsPublish extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelTsPublishService::class;
    }
}
