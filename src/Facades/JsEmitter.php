<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Facades;

use AbeTwoThree\LaravelTsPublish\Support\JsEmitter as JsEmitterService;
use Illuminate\Support\Facades\Facade;

/**
 * @see JsEmitterService
 *
 * @internal
 */
class JsEmitter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JsEmitterService::class;
    }
}
