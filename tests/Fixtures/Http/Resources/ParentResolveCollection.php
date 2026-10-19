<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\TagResource;

/**
 * A test-only collection whose `resolve()` returns `parent::resolve()`, which runs its own `toArray()`.
 */
class ParentResolveCollection extends ResourceCollection
{
    public $collects = TagResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['data' => $this->collection, 'count' => $this->count()];
    }

    /** @return array<int|string, mixed> */
    public function resolve($request = null): array
    {
        return parent::resolve($request);
    }
}
