<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ImageableSwapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block passes `$this` to a helper that may replace it.
 *
 * @mixin Image
 */
final class ImageableThisPassGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            ImageableSwapper::swapResource($this);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
