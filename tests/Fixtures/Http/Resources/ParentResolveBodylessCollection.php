<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\TagResource;

/**
 * A test-only collection that keeps ResourceCollection's `toArray()`, so `parent::resolve()` is the bare list of
 * collected items; its `with()` reads that list and `parent::toArray()` as values, and its `jsonSerialize()` builds on
 * the list through its own `resolve()`.
 */
class ParentResolveBodylessCollection extends ResourceCollection
{
    public $collects = TagResource::class;

    /** @return array<int|string, mixed> */
    public function resolve($request = null): array
    {
        return parent::resolve($request);
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['items' => parent::resolve($request), 'list' => parent::toArray($request)];
    }

    /** @return array<int|string, mixed> */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['count'] = 2;

        return $data;
    }
}
