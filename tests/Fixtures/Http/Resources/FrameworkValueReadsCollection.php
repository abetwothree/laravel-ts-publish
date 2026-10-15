<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\TagResource;

/**
 * A test-only collection that keeps JsonResource's `resolve()`, the bare list of collected items, and reads it as a
 * value and as a spread.
 */
class FrameworkValueReadsCollection extends ResourceCollection
{
    public $collects = TagResource::class;

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['snapshot' => $this->resolve($request)];
    }

    /** @return array<int|string, mixed> */
    public function meta($request): array
    {
        return [...$this->resolve($request), 'count' => 2];
    }
}
