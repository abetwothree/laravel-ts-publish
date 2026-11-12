<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An undocumented body spreading a helper whose own `@return` types the argument it is passed (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillDocumentedArgumentResource extends JsonResource
{
    /** The helper's keys, under no `@return`. */
    public function toArray(Request $request): array
    {
        return [...$this->tagged(array_map(fn (GapFillRow $r) => $r->ok ? cache('k') : null, $this->resource->rows))];
    }

    /**
     * The tags the caller passes, under their key.
     *
     * @return array{tags: list<list<int>|null>}
     */
    private function tagged(array $tags): array
    {
        return ['tags' => $tags];
    }
}
