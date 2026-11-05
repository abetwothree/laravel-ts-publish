<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose generic declares it holds a post or null.
 *
 * @extends JsonResource<Post|null>
 */
final class NullablePostExtendsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->resource->title,
            'proxy_title' => $this->title,
            'id' => $this->resource->getKey(),
            'whole' => $this->resource,
        ];
    }
}
