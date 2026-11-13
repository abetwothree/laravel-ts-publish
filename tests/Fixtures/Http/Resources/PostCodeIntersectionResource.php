<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose generic declares it holds a post that also implements an interface declaring properties.
 *
 * @extends JsonResource<Post&HasCode>
 */
final class PostCodeIntersectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'title' => $this->resource->title,
            'stats_total' => $this->resource->stats->total,
            'proxy_stats_total' => $this->stats->total,
            'code_stats_total' => $this->resource->codeStats()->total,
        ];
    }
}
