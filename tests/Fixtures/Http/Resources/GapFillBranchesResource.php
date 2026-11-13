<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Two returns, each beside a helper's keys the `@return` does not type, one leaving out an arm of a typed key (GH-154).
 *
 * @property-read GapFillRow $resource
 */
final class GapFillBranchesResource extends JsonResource
{
    /** @return array{k: list<int>|string} */
    public function toArray(Request $request): array
    {
        if ($this->resource->ok) {
            return ['k' => $this->resource->id > 0 ? cache('k') : 'a', ...$this->extras()];
        }

        return ['k' => 'b', ...$this->extras()];
    }

    /** A key beside the typed one, leaving out an arm no rule types. */
    private function extras(): array
    {
        return ['loose' => $this->resource->id > 1 ? cache('l') : 'c'];
    }
}
