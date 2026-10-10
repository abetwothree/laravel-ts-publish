<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose local holds a `whenHas()` value, built by a closure whose parameter shares its name.
 *
 * @mixin Post
 */
class EnumResourceShadowedParameterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->whenHas('status', fn ($status) => EnumResource::make($status));

        return ['status' => $status];
    }
}
