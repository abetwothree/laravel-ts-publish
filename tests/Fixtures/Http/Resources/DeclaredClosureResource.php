<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MapOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Value closures whose parameters or returns only a declaration types: a `@param` class, a nullable class spelled
 * either way, and a native `array` or union return beside a precise `@return`.
 *
 * @mixin Post
 */
class DeclaredClosureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'native_param_titles' => collect($this->opaque([$this->resource]))->map(fn (Post $p) => $p->title)->all(),
            'doc_param_titles' => collect($this->opaque([$this->resource]))->map(/** @param Post $p */ fn ($p) => $p->title)->all(),
            'doc_param_item_ids' => collect($this->opaque([new MapOption]))->map(/** @param MapOption $o */ fn ($o) => $o->itemId)->all(),
            'nullable_titles' => collect($this->opaque([$this->resource]))->map(fn (?Post $p) => $p?->title)->all(),
            'or_null_titles' => collect($this->opaque([$this->resource]))->map(fn (?Post $p) => $p?->title)->all(),
            'shape_return' => $this->when(true, /** @return array{a: int} */ fn (): array => $this->opaque(['a' => 1])),
            'union_return' => $this->when(true, /** @return array{a: int} */ fn (): array|string => $this->opaque(['a' => 1])),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
