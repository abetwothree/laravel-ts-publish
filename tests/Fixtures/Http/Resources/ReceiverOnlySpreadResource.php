<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Top-level spreads of a relation's own filters, which flatten like the resource's own `only()`.
 *
 * @mixin Post
 */
final class ReceiverOnlySpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->author->only(['id', 'name']),
            ...$this->resource->author->only(['email']),
            ...$this->author->except(['password']),
            'nested' => [...$this->author->only(['id']), 'x' => 1],
        ];
    }
}
