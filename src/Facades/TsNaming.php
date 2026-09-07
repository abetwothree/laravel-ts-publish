<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Facades;

use AbeTwoThree\LaravelTsPublish\Support\TsNaming as TsNamingService;
use Illuminate\Support\Facades\Facade;

/**
 * @see TsNamingService
 *
 * @internal
 */
class TsNaming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TsNamingService::class;
    }
}
