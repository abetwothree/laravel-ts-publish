<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block includes a file that swaps it.
 *
 * @mixin Image
 */
final class ImageableIncludeGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            include dirname(__DIR__, 2).'/Stubs/ImageableSwapInclude.php.stub';

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
