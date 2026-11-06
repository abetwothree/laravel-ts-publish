<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\UuidPost;

/**
 * Proves its image's post, then swaps it through a call naming its own class, which PHP runs on `$this`.
 *
 * @extends JsonResource<Image>
 */
final class OwnNameSwapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->imageable instanceof Post) {
            self::swap();

            return ['id' => $this->imageable->getKey()];
        }

        return ['id' => null];
    }

    /** Swaps the image's post for a string-keyed one. */
    public function swap(): void
    {
        $this->resource->setRelation('imageable', (new UuidPost)->forceFill(['uuid' => 'abc']));
    }
}
