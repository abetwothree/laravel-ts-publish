<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\User;
use Workbench\App\Models\UuidPost;

/**
 * Proves a post a second chain holds, then swaps that post's author by key through the first chain, which holds it too.
 *
 * @extends JsonResource<Image>
 */
final class AliasedKeySwapResource extends JsonResource
{
    /** The post the image holds, read again through this second chain. */
    public mixed $post = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->post = $this->resource->imageable;

        if ($this->post->author instanceof User) {
            $this->resource->imageable['author'] = (new UuidPost)->forceFill(['uuid' => 'abc']);

            return ['id' => $this->post->author->getKey()];
        }

        return ['id' => null];
    }
}
