<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource the convention would back with EagerColumnsPost, whose mixin names an interface no model implements.
 *
 * @mixin Htmlable
 */
final class EagerColumnsPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title];
    }
}
