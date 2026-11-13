<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose flagged return writes keys twice, each path folded on its own, beside a return that
 * writes none of them.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldReturnsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $maybe = [];

        if ($request->boolean('picked')) {
            $maybe['local'] = $this->resource->title;
        }

        if ($request->boolean('flagged')) {
            return [
                'id' => 1,
                $this->mergeWhen($request->boolean('picked'), ['by' => $this->resource->title]),
                ...$this->authoredBy(),
                ...$this->authoredHelper(),
                ...$this->maybeTitledHelper($request),
                'key' => $this->resource->author,
                ...$this->maybeTitledKey($request),
                $this->mergeWhen($request->boolean('picked'), ['merged' => $this->resource->title]),
                'merged' => $this->resource->author,
                'local' => $this->resource->author,
                ...$maybe,
            ];
        }

        return ['n' => 1];
    }

    /** @return array<string, mixed> */
    private function authoredBy(): array
    {
        return ['by' => $this->resource->author];
    }

    /** @return array<string, mixed> */
    private function authoredHelper(): array
    {
        return ['helper' => $this->resource->author];
    }

    /** @return array<string, mixed> */
    private function maybeTitledHelper(Request $request): array
    {
        $out = [];

        if ($request->boolean('picked')) {
            $out['helper'] = $this->resource->title;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function maybeTitledKey(Request $request): array
    {
        $out = [];

        if ($request->boolean('picked')) {
            $out['key'] = $this->resource->title;
        }

        return $out;
    }
}
