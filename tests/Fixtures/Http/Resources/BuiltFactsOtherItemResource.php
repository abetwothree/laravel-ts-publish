<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only item resource sending `b`, unrelated to BuiltFactsItemResource. */
class BuiltFactsOtherItemResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return ['b' => 'x'];
    }
}
