<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\FoldBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource writing keys through a mergeWhen() or mergeUnless(), then again through a later write of each
 * kind, which the merged value beats wherever the merge ran.
 *
 * @property-read FoldBox $resource
 */
final class SpreadFoldMergeWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $authored = ['local' => $this->resource->author];
        $titled = ['unless' => $this->resource->title];

        return [
            'id' => 1,
            $this->mergeWhen($request->boolean('picked'), ['helper' => $this->resource->title]),
            ...$this->helperAuthor(),
            $this->mergeWhen($request->boolean('picked'), ['local' => $this->resource->title]),
            ...$authored,
            $this->mergeWhen($request->boolean('picked'), ['literal' => $this->resource->title]),
            ...['literal' => $this->resource->author],
            $this->mergeWhen($request->boolean('picked'), ['u' => $this->resource->author]),
            'u' => $this->resource->crmAuthor,
            $this->mergeWhen($request->boolean('flagged'), ['removable' => $this->when($request->boolean('picked'), $this->resource->title)]),
            'removable' => $this->resource->author,
            $this->mergeUnless($request->boolean('flagged'), [...$titled, 'unless' => $this->resource->author]),
            $this->mergeWhen($request->boolean('picked'), ['later_when' => $this->resource->title]),
            'later_when' => $this->when($request->boolean('flagged'), $this->resource->author),
        ];
    }

    /** @return array<string, mixed> */
    private function helperAuthor(): array
    {
        return ['helper' => $this->resource->author];
    }
}
