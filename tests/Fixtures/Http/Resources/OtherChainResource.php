<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource outside PrivateChainBaseResource's hierarchy, whose public `chain()` returns an array. */
class OtherChainResource extends JsonResource
{
    /** @return array<string, int> */
    public function chain(): array
    {
        return ['o' => 1];
    }
}
