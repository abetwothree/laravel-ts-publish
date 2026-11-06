<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose attribute names its model over a mixin naming a value object that model is not.
 *
 * @mixin HeldStats
 */
#[TsResource(model: Post::class)]
final class ExplicitPostOverHeldStatsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'key' => $this->getKey(),
            'res_key' => $this->resource->getKey(),
            'whole' => $this->resource,
        ];
    }
}
