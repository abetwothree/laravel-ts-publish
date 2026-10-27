<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Spreads the helper DeclaredListBaseCollection declares, whose `parent::toArray()` is ResourceCollection's list. */
class DeclaredListCollection extends DeclaredListBaseCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->listed($request)];
    }
}
