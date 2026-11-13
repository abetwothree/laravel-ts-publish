<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block calls only what cannot reach it: a static helper given its title
 * and `when()` given a closure that reads it.
 *
 * @mixin Image
 */
final class ImageableSafeCallsGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            return ['t' => Str::limit($this->imageable->title, 50), 'w' => $this->when(true, fn () => $this->imageable->title)];
        }

        return ['t' => null, 'w' => null];
    }
}
