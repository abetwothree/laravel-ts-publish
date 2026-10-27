<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** ForwardedBuildResource's sibling: the same helper's `self::make()` builds this class, with its own summary(). */
class ForwardedBuildSiblingResource extends ForwardedBuildBaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->built()];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return ['sibling_summary' => 'x'];
    }
}
