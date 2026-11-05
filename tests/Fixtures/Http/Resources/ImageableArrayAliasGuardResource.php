<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;

/**
 * An `if` testing a `$this->prop` morphTo whose block swaps it through an array holding `$this->resource`.
 *
 * @mixin Image
 */
final class ImageableArrayAliasGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pair = [$this->resource];

        if ($this->imageable instanceof Post) {
            $pair[0]->setRelation('imageable', $pair[0]->reviewable);

            return ['k' => $this->imageable?->getKey()];
        }

        return ['k' => null];
    }
}
