<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * A negated `instanceof` test on a `$this->prop` morphTo that returns early, then reads through it past the guard.
 *
 * @mixin Image
 */
final class ImageableEarlyGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->imageable instanceof Post) {
            return ['t' => null];
        }

        return ['t' => $this->imageable->title];
    }
}
