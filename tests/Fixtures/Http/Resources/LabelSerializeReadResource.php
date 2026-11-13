<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelSerializePost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads each serializing cast of its post off the model, through the proxy and the resource, which sends get()'s
 * value; and the whole model, whose toArray() sends serialize()'s.
 *
 * @mixin LabelSerializePost
 */
final class LabelSerializeReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'method_label' => $this->title,
            'method_label_whole' => $this->resource->title,
            'interface_label' => $this->content,
            'interface_label_whole' => $this->resource->content,
            'mixed_interface' => $this->category,
            'number' => $this->user_id,
            'mixed_method' => $this->status,
        ];
    }
}
