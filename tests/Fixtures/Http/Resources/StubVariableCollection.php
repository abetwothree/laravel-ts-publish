<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Writes a key into a variable holding `parent::toArray()`. */
class StubVariableCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        $items = parent::toArray($request);
        $items['extra'] = 1;

        return $items;
    }
}
