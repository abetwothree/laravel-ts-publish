<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ImageableSwapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block gives `$this->resource` to a `can()` that is no `Authorizable`.
 *
 * @mixin Image
 */
final class ImageableAbilityGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            (new ImageableSwapper)->can('swap', $this->resource);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
