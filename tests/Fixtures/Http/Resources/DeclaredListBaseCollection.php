<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\PostResource;

/** A collection whose toArray() wraps the list, while its helpers hand back ResourceCollection's through `parent::`. */
class DeclaredListBaseCollection extends ResourceCollection
{
    public $collects = PostResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['wrapped' => $this->collection];
    }

    /** @return array<string, mixed> */
    protected function listed(Request $request): array
    {
        return ['items' => parent::toArray($request)];
    }

    /** @return array<int|string, mixed> */
    protected function spreadList(Request $request): array
    {
        return [...parent::toArray($request), 'count' => $this->collection->count()];
    }
}
