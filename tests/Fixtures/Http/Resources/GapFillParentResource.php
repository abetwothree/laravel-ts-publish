<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An undocumented resource whose rows hold a value no rule types or null, which another resource's `@return` types.
 *
 * @property-read GapFillBox $resource
 */
class GapFillParentResource extends JsonResource
{
    /** The rows each tag, under no `@return`. */
    public function toArray(Request $request): array
    {
        return ['tags' => array_map(fn (GapFillRow $r) => $r->ok ? cache('k') : null, $this->resource->rows)];
    }
}
