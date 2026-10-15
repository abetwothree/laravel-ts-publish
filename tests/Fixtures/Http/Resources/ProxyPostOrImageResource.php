<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/** A resource that redeclares `$resource` as one of two models, and reads it as a post once a test proves one. */
final class ProxyPostOrImageResource extends JsonResource
{
    /** @var Post|Image */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'post' => $this->resource instanceof Post ? $this->resource : null,
            'author_key' => $this->resource instanceof Post ? $this->author->getKey() : null,
        ];
    }
}
