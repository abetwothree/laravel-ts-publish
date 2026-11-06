<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block passes `$this->resource` to an authorization check.
 *
 * @mixin Image
 */
final class ImageableAuthorizedGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            $request->user()?->cannot('view', $this->resource);

            return ['k' => $this->imageable->getKey()];
        }

        return ['k' => null];
    }
}
