<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns;

/** A test-only trait whose `retag()` writes the `$with` of the resource that uses it. */
trait RetagsWith
{
    /** Replace the top-level keys the response carries. */
    public function retag(): static
    {
        $this->with = ['other' => 1];

        return $this;
    }
}
