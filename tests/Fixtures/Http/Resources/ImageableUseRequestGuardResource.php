<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block hands a closure importing `$request` an ability check on it.
 *
 * @mixin Image
 */
final class ImageableUseRequestGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            collect([1])->each(function () use ($request): void {
                $request->user()?->can('view', $this->resource);
            });

            return ['k' => $this->imageable->getKey()];
        }

        return ['k' => null];
    }
}
