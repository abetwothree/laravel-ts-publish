<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Facades;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use Illuminate\Support\Facades\Facade;

/**
 * @see TsTypeStringService
 *
 * @internal
 */
class TsTypeString extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TsTypeStringService::class;
    }
}
