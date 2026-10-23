<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyCollectedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Two helpers spread under an `instanceof` proof on the items: one writes an unrelated property, one replaces them.
 *
 * @mixin ProxyCollectedPost
 */
final class RewrittenPageCollection extends ResourceCollection
{
    public $collects = ProxyCollectedPostResource::class;

    public ?string $marker = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'marked' => $this->resource instanceof LengthAwarePaginator ? [...$this->markedPage()] : null,
            'replaced' => $this->resource instanceof LengthAwarePaginator ? [...$this->replacedPage()] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function markedPage(): array
    {
        $this->marker = 'seen';

        return ['pager' => $this->resource];
    }

    /** @return array<string, mixed> */
    private function replacedPage(): array
    {
        $this->resource = $this->collection;

        return ['pager' => $this->resource];
    }
}
