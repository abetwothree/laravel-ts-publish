<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Collects PageItemResource, and picks at random off a variable a branch reassigns from `$this->collection`. */
class BranchedItemsPostCollection extends ResourceCollection
{
    public $collects = PageItemResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $picked = $this->collection->pluck('title');

        if ($request->boolean('ids')) {
            $picked = $this->collection->pluck('id');
        }

        return [
            'picked' => $picked->random(),
            'picked_pair' => $picked->random(2),
        ];
    }
}
