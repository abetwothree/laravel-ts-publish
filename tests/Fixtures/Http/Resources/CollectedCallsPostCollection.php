<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Collects PageItemResource, and calls Support\Collection's methods on `$this->collection`. */
class CollectedCallsPostCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $count = $request->has('count') ? 2 : null;

        return [
            'to_array' => $this->collection->toArray(),
            'json' => $this->collection->jsonSerialize(),
            'first' => $this->collection->first(),
            'last' => $this->collection->last(),
            'only' => $this->collection->only([0]),
            'except' => $this->collection->except([0]),
            'is_empty' => $this->collection->isEmpty(),
            'contains' => $this->collection->contains('id', 1),
            'implode' => $this->collection->implode('title', ','),
            'to_json' => $this->collection->toJson(),
            'avg' => $this->collection->avg('id'),
            'median' => $this->collection->median('id'),
            'percentage' => $this->collection->percentage(fn ($item) => $item->id > 1),
            'mode' => $this->collection->mode('id'),
            'random' => $this->collection->random(),
            'random_null' => $this->collection->random(null),
            'random_two' => $this->collection->random(2),
            'random_kept' => $this->collection->random(2, true),
            'random_counted' => $this->collection->random(fn () => 1),
            'random_maybe' => $this->collection->random($count),
        ];
    }
}
