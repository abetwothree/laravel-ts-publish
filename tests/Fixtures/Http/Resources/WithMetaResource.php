<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource sending a post's id and title, whose `with()` adds a `meta` key beside them in its response.
 *
 * @mixin Post
 */
class WithMetaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
        ];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['meta' => ['v' => 1]];
    }
}
