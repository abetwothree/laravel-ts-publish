<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose class name spells "null", with a method that may hand no instance back.
 */
class AnnulledResource extends JsonResource
{
    /** This same instance, or null when it wraps nothing. */
    public function maybe(): ?static
    {
        return $this->resource === null ? null : $this;
    }
}
