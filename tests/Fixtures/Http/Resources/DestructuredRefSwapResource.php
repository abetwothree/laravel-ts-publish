<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\UuidPost;

/**
 * Proves the post its own property holds, then writes a local a destructuring bound to that property by reference.
 *
 * @extends JsonResource<Image>
 */
final class DestructuredRefSwapResource extends JsonResource
{
    /** The post the image holds. */
    public mixed $post = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->post = $this->resource->imageable;
        $list = [&$this->post];
        [&$ref] = $list;

        if ($this->post instanceof Post) {
            $ref = (new UuidPost)->forceFill(['uuid' => 'abc']);

            return ['id' => $this->post->getKey()];
        }

        return ['id' => null];
    }
}
