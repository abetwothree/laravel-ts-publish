<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An ancestor that calls a method it lacks through `self::` and `static::`, which only a subclass declares.
 */
class MacroAncestorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['m' => self::ancestorProbe(), 'n' => static::ancestorProbe()];
    }
}
