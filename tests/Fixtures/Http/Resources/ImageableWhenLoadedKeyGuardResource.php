<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block hands that morph to a `whenLoaded()` callback reading its key.
 *
 * @mixin Image
 */
final class ImageableWhenLoadedKeyGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            return ['loaded' => $this->whenLoaded('imageable', fn (Model $imageable) => $imageable->getKey()), 'k' => $this->imageable?->getKey()];
        }

        return ['loaded' => null, 'k' => null];
    }
}
