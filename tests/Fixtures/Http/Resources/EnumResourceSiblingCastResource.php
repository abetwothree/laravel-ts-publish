<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose cast builds on the type name of an enum another key only wraps.
 *
 * @mixin Post
 */
#[TsCasts(['k' => ['type' => 'Record<string, StatusType>', 'import' => '@/types/meta']])]
class EnumResourceSiblingCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['k' => $this->title, 'st' => EnumResource::make($this->status)];
    }
}
