<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyCollectedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A collection that tests its items for a length-aware paginator in an `if`, and sends them only where the test holds.
 *
 * @mixin ProxyCollectedPost
 */
final class ProxyBranchPageCollection extends ResourceCollection
{
    public $collects = ProxyCollectedPostResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('nested')) {
            if (! $this->resource instanceof LengthAwarePaginator) {
                return ['pager' => null, 'nested' => true];
            }

            return ['pager' => $this->resource, 'nested' => true];
        }

        if ($this->resource instanceof LengthAwarePaginator) {
            return ['pager' => $this->resource, 'nested' => false];
        }

        return ['pager' => null, 'nested' => false];
    }
}
