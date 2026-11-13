<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spreads an undocumented resource's resolved keys, which its own `@return` types (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillResourceArmResource extends JsonResource
{
    /** @return array{tags: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        return [...GapFillParentResource::make($this->resource)->resolve($request)];
    }
}
