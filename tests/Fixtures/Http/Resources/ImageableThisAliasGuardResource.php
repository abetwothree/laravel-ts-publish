<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ImageableSwapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block gives a helper a local holding `$this`, which may replace it.
 *
 * @mixin Image
 */
final class ImageableThisAliasGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $self = $this;

        if ($this->imageable instanceof Post) {
            ImageableSwapper::swapResource($self);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
