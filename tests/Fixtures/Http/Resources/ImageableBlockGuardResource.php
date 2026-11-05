<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` that tests a `$this->prop` morphTo and returns what its block reads through it.
 *
 * @mixin Image
 */
final class ImageableBlockGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            return ['t' => $this->imageable->title, 'id' => $this->imageable->getKey(), 'kind' => 'post'];
        }

        return ['t' => null, 'id' => null, 'kind' => 'other'];
    }
}
