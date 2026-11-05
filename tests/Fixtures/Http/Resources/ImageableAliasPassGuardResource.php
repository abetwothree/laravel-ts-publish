<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block gives `when()` a closure, made before the test, that replaces it.
 *
 * @mixin Image
 */
final class ImageableAliasPassGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $swap = function (): void {
            $this->resource->setRelation('imageable', $this->resource->reviewable);
        };

        if ($this->imageable instanceof Post) {
            $this->when(true, $swap);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
