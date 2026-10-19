<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with one key: an inline array whose member is a `when()` over two enum resources.
 *
 * @mixin Post
 */
class EnumResourceArmsInlineWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'state' => ['inner' => $this->when(
                $request->boolean('status'),
                EnumResource::make($this->status),
                EnumResource::make($this->visibility),
            )],
        ];
    }
}
