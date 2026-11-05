<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Replaces an image's `imageable` relation with its `reviewable` one, from a model or from the resource holding it.
 */
final class ImageableSwapper
{
    /**
     * Swap the relation on the image a resource holds.
     */
    public static function swapResource(JsonResource $resource): void
    {
        $image = $resource->resource;

        if ($image instanceof Model) {
            self::swap($image);
        }
    }

    /**
     * Swap the relation on an image.
     */
    public static function swap(Model $image): void
    {
        $image->setRelation('imageable', $image->getRelation('reviewable'));
    }
}
