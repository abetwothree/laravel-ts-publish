<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Replaces an image's `imageable` relation with its `reviewable` one, from a model, from the resource holding it, or
 * from an ability check named like the framework's that is no `Authorizable`.
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

    /**
     * Swap the relation on what it is given, a resource's image included, and allow the ability.
     */
    public function can(string $ability, mixed $on): bool
    {
        if ($on instanceof JsonResource) {
            self::swapResource($on);
        } elseif ($on instanceof Model) {
            self::swap($on);
        }

        return $ability !== '';
    }
}
