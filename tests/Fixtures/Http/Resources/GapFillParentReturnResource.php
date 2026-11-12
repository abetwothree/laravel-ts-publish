<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Returns its undocumented parent's keys whole, which its own `@return` types (GH-154). */
final class GapFillParentReturnResource extends GapFillParentResource
{
    /** @return array{tags: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
