<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource the naming convention backs with ListingPost, whose IDE-helper mixin names the Eloquent alias.
 *
 * @mixin \Eloquent
 */
final class ListingPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title];
    }
}
