<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Spreads its undocumented parent's keys, which its own `@return` types (GH-154). */
final class GapFillParentSpreadResource extends GapFillParentResource
{
    /** @return array{tags: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'extra' => true];
    }
}
