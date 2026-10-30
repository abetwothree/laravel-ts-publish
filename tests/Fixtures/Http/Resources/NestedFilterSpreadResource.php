<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** A test-only resource whose `toArray()` spreads its parent's `nestedFilter()` through `parent::`. */
final class NestedFilterSpreadResource extends NestedUnreadSpreadBaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...parent::nestedFilter()];
    }
}
