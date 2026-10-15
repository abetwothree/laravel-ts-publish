<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;
use Workbench\App\Models\Post;

/**
 * A post resource that redeclares `$resource` as a media type or its post, so both may answer.
 *
 * @mixin Post
 */
final class ProxyMediaOrPostResource extends JsonResource
{
    /** @var MediaType|Post */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'max' => $this->maxSizeMb(),
            'max_when_media' => $this->resource instanceof MediaType ? $this->resource->maxSizeMb() : null,
        ];
    }
}
