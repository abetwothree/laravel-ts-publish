<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Builds another API resource around its post, and reads members the built one forwards to that post.
 *
 * @extends JsonResource<Post>
 */
class ItemBuildingPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $wrapped = PageItemResource::make($this->resource);

        return [
            'make_title' => PageItemResource::make($this->resource)->title,
            'make_id' => PageItemResource::make($this->resource)->id,
            'new_title' => (new PageItemResource($this->resource))->title,
            'local_title' => $wrapped->title,
            'local_key' => $wrapped->getKey(),
            'local_model_title' => $wrapped->resource->title,
            'local_copy_title' => $wrapped->replicate()->title,
        ];
    }
}
