<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` that tests a `$this->prop` morphTo, then replaces it in its block before the block reads through it.
 *
 * @mixin Image
 */
final class ImageableRewriteGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            $this->imageable = $this->resource->reviewable;

            return ['t' => $this->imageable?->title];
        }

        return ['t' => null];
    }
}
