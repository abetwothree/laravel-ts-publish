<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\PostResource;

/**
 * Its own toArray() lacks the wrap key, so Laravel's response nests the whole body under that key.
 */
class ItemsKeyPostCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = PostResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->collection,
            'count' => $this->collection->count(),
        ];
    }
}
