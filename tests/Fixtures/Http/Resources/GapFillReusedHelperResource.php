<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vague helper spreading one whose `@return` fills its gap, read twice: the second read reuses the first (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillReusedHelperResource extends JsonResource
{
    /** The vague helper's value under two undocumented keys. */
    public function toArray(Request $request): array
    {
        return ['meta' => $this->outer(), 'more' => $this->outer()];
    }

    /** The documented helper's keys, under a vague return type. */
    private function outer(): array
    {
        return [...$this->part()];
    }

    /**
     * A key its own `@return` types, whose value leaves a gap.
     *
     * @return array{a: list<int>|null}
     */
    private function part(): array
    {
        return ['a' => $this->resource->rows !== [] ? cache('l') : null];
    }
}
