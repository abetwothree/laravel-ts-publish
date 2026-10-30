<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only item resource sending `a`, which BuiltFactsBaseCollection collects. */
class BuiltFactsItemResource extends JsonResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return ['a' => 1];
    }
}
