<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Wraps a post's status in EnumResource through a relation chain and a nullsafe read of the relation.
 *
 * @extends JsonResource<Comment>
 */
final class EnumResourceRelationChainResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chain' => EnumResource::make($this->resource->post->status),
            'nullsafe' => EnumResource::make($this->resource->post?->status),
        ];
    }
}
