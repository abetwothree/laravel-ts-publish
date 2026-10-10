<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose method casts retype the numeric keys its body also writes, which PHP stores as ints.
 */
class NumericMethodCastResource extends JsonResource
{
    /** @return array<array-key, mixed> */
    #[TsCasts(['42' => 'boolean', '6' => 'number'])]
    public function toArray(Request $request): array
    {
        return ['id' => 1, 42 => 1, '6' => 'x', 'nested' => [42 => 'x']];
    }
}
