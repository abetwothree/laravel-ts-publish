<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whether a class a type names has a generated file to import it from.
 */
final class PublishedClasses
{
    /**
     * Whether a generated file exports the class, as far as this run knows: a model or resource when it is published
     * (every one while no set is registered), a plain class never.
     */
    public static function exports(string $fqcn): bool
    {
        // The resource registry matches names exactly, so a leading backslash would miss its set.
        $fqcn = ltrim($fqcn, '\\');

        // A non-class name cannot be judged, and an enum's name travels on its own channel: both stay as spelled.
        if (! class_exists($fqcn) || enum_exists($fqcn)) {
            return true;
        }

        if (is_a($fqcn, Model::class, true)) {
            return PublishedModelRegistry::isPublished($fqcn);
        }

        return is_a($fqcn, JsonResource::class, true) && PublishedResourceRegistry::isPublished($fqcn);
    }
}
