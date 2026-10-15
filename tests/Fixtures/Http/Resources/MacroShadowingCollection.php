<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A collection whose bare calls name resource macros, which every collection shares with JsonResource. */
class MacroShadowingCollection extends ResourceCollection
{
    public $collects = MacroShadowingResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label(),
            'held' => $this->resource,
            'held_rows' => $this->heldRows(),
            'data' => $this->collection,
        ];
    }
}
