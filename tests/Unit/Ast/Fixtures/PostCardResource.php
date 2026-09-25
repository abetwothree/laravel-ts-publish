<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Two sections that each spread PostBadgeResource, so an analysis of this one reads the badge twice. */
final class PostCardResource extends JsonResource
{
    /** Both sections. */
    public function toArray(Request $request): array
    {
        return [...$this->header(), ...$this->footer()];
    }

    /** The badge, marked as the header. */
    private function header(): array
    {
        return [...PostBadgeResource::make($this->resource)->resolve(), 'header' => true];
    }

    /** The badge, marked as the footer. */
    private function footer(): array
    {
        return [...PostBadgeResource::make($this->resource)->resolve(), 'footer' => true];
    }
}
