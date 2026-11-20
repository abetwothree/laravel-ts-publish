<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Unsets a public property of the post it holds, so a later write of it reaches the post's `__set()`.
 *
 * @mixin Post
 */
final class PinForgettingResource extends JsonResource
{
    /**
     * Forgets the post's open note.
     */
    public function forget(): void
    {
        unset($this->resource->pin_open);
    }
}
