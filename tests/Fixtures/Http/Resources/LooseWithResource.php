<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource sending a post's id and title, whose `with()` returns keys from a static property no read types.
 *
 * @mixin Post
 */
class LooseWithResource extends JsonResource
{
    /** @var array<string, mixed> */
    public static array $extra = ['meta' => 1];

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
        return static::$extra;
    }
}
