<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Model reads under typed keys: an accessor whose body is read, and a column whose class cast's get() is read. */
final class GapModeModelReadsResource extends JsonResource
{
    /**
     * An accessor and a cast column, each under a typed key.
     *
     * @return array{summary: string, label: string}
     */
    public function toArray($request): array
    {
        return ['summary' => $this->resource->summary, 'label' => $this->resource->content];
    }
}
