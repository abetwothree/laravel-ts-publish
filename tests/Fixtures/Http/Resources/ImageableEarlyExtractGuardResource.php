<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\ImageableSwapperRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo, after the body `extract()`s a `$request` whose `can()` swaps it.
 *
 * @mixin Image
 */
final class ImageableEarlyExtractGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        extract(['request' => new ImageableSwapperRequest]);

        if ($this->imageable instanceof Post) {
            $request->user()->can('swap', $this->resource);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
