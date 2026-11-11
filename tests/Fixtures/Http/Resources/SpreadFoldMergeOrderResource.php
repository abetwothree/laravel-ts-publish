<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource writing keys before, inside and after merge() calls, which Laravel lifts over later keys.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldMergeOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'held' => $this->resource->author,
            $this->merge(['held' => $this->resource->title]),
            ...$this->maybeHeld($request),
            $this->merge(['maybe_held' => $this->resource->title]),
            'gone' => $this->when($request->boolean('picked'), $this->resource->author),
            $this->merge(['gone' => $this->resource->title]),
            $this->merge(['merged' => $this->resource->title]),
            'merged' => $this->resource->author,
            $this->merge(['beats_helper' => $this->resource->title]),
            ...$this->helperAuthor(),
            $this->merge(['dropped' => $this->when($request->boolean('picked'), $this->resource->title)]),
            'dropped' => $this->resource->author,
            $this->mergeWhen($request->boolean('picked'), ['twice' => $this->resource->title]),
            $this->merge(['twice' => $this->resource->author]),
            $this->merge(['first' => $this->resource->title]),
            $this->merge(['first' => $this->resource->author]),
        ];
    }

    /** @return array<string, mixed> */
    private function maybeHeld(Request $request): array
    {
        $out = [];

        if ($request->boolean('picked')) {
            $out['maybe_held'] = $this->resource->author;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function helperAuthor(): array
    {
        return ['beats_helper' => $this->resource->author];
    }
}
