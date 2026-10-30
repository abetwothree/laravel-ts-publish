<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of PrivateChainBaseResource's `toArray()`, whose public `chain()` returns an array. */
class PrivateChainResource extends PrivateChainBaseResource
{
    /** @return array<string, int> */
    public function chain(): array
    {
        return ['k' => 1];
    }
}
