<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** A test-only resource whose `toArray()` spreads its parent's `toArray()` and `extra()` through `parent::`. */
class SpreadingBaseResource extends SpreadingGrandResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), ...parent::extra(), 'b' => true];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['be' => true];
    }
}
