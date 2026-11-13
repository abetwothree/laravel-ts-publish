<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource the naming convention backs with CountedPost, whose mixin names one interface the model implements.
 *
 * @mixin HasLabel&Arrayable
 */
final class CountedPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'label' => $this->label(),
            'id' => $this->getKey(),
            'whole' => $this->resource,
        ];
    }
}
