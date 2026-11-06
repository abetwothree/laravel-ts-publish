<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LabeledHolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource over a value object whose properties are natively typed as intersections.
 *
 * @extends JsonResource<LabeledHolder>
 */
final class LabeledHolderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'post_label' => $this->resource->post->label(),
            'post_title' => $this->resource->post->title,
            'proxy_post_label' => $this->post->label(),
            'entry_label' => $this->resource->entry?->label(),
            'entry_id' => $this->resource->entry?->getKey(),
            'primary_label' => $this->resource->primary()->label(),
        ];
    }
}
