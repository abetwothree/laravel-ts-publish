<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Spreads DeclaredListBaseCollection's helper, which spreads ResourceCollection's list through `parent::`. */
class DeclaredSpreadListCollection extends DeclaredListBaseCollection
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->spreadList($request)];
    }
}
