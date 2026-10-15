<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** A resource that redeclares `$resource` as a post or a string; the pipeline backs it with that post. */
final class ProxyPostOrStringResource extends JsonResource
{
    /** @var Post|string */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['key' => $this->resource->getKey()];
    }
}
