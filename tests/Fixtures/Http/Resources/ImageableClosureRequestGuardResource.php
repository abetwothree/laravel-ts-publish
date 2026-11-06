<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ImageableSwapperRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block hands a closure a `$request` that is no Request, which swaps it.
 *
 * @mixin Image
 */
final class ImageableClosureRequestGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            collect([new ImageableSwapperRequest])->each(function (ImageableSwapperRequest $request): void {
                $request->user()->can('swap', $this->resource);
            });

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
