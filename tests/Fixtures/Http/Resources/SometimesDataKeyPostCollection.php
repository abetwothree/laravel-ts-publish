<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\PostResource;

/**
 * Its toArray() sends the wrap key only on some requests, so Laravel's response nests the body only on the others.
 */
class SometimesDataKeyPostCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = PostResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->when($request->boolean('full'), $this->collection),
            'count' => $this->collection->count(),
        ];
    }
}
