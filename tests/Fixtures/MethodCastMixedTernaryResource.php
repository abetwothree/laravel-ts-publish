<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose toArray() #[TsCasts] names none of the enums its mixed ternary reads.
 *
 * @mixin Post
 */
class MethodCastMixedTernaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['k' => 'string'])]
    public function toArray(Request $request): array
    {
        return [
            'k' => $request->boolean('a') ? EnumResource::make($this->status) : $this->status,
        ];
    }
}
