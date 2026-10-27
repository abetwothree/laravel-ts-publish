<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SelfHelperPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource sending what SelfHelperPost's inherited helpers return.
 */
#[TsResource(model: SelfHelperPost::class)]
class SelfHelperPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'via_self' => $this->resource->viaSelf(),
            'via_self_shape' => $this->resource->viaSelfShape(),
            'via_self_only' => $this->resource->viaSelfOnly(),
            'via_private' => $this->resource->viaPrivate(),
        ];
    }
}
