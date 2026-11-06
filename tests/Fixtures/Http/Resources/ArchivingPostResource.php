<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource the naming convention backs with ArchivingPost, whose mixin names an interface every model implements.
 *
 * @mixin Arrayable
 */
final class ArchivingPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'whole' => $this->resource,
            'guarded' => $this->resource instanceof HeldStats ? $this->summary() : $this->getKey(),
        ];
    }
}
