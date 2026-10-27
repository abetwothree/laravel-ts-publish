<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Spreads ForwardedBuildBaseResource's helper, whose `self::make()` builds this class, with its own summary(). */
class ForwardedBuildResource extends ForwardedBuildBaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->built()];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return ['child_summary' => true];
    }
}
