<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Declares its own `title`, which PHP reads before Laravel's `__get()` could forward its post's.
 *
 * @mixin Post
 */
class OwnTitleItemResource extends JsonResource
{
    public bool $title = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [];
    }
}
