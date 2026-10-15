<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

/**
 * A resource collection's facts, read off the class Laravel instantiates: the API resource it collects, whether it
 * keeps its source keys, the list its items publish as, and whether its body is Laravel's own. Static so the
 * publisher's re-index can ask of a class it never analyzes.
 *
 * @internal
 */
final class ResourceCollections
{
    /**
     * Resolve the API resource a ResourceCollection collects, from the #[Collects] attribute, the $collects property
     * default, or the FooCollection → FooResource naming convention.
     *
     * @param  class-string  $collectionFqcn
     * @return class-string<JsonResource>|null
     */
    public static function collectedResource(string $collectionFqcn): ?string
    {
        $reflection = new ReflectionClass($collectionFqcn);

        $collectsAttribute = 'Illuminate\Http\Resources\Attributes\Collects';
        if (class_exists($collectsAttribute)) {
            // Priority 1: #[Collects] attribute (Laravel 13.0+)
            $collectsAttrs = $reflection->getAttributes($collectsAttribute);

            if ($collectsAttrs !== []) {
                $collectsClass = $collectsAttrs[0]->newInstance()->class;

                if (class_exists($collectsClass) && is_a($collectsClass, JsonResource::class, true)) {
                    return $collectsClass;
                }
            }
        }

        // Priority 2: explicit $collects property default value
        /** @var array<string, mixed> $defaults */
        $defaults = $reflection->getDefaultProperties();
        $collects = $defaults['collects'] ?? null;

        if (is_string($collects) && class_exists($collects) && is_a($collects, JsonResource::class, true)) {
            return $collects;
        }

        // Priority 3: naming convention — FooCollection → FooResource, gated on the published set
        $className = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();

        if (str_ends_with($className, 'Collection')) {
            $base = substr($className, 0, -10);

            $candidate = $namespace.'\\'.$base.'Resource';

            if (self::isPublishedResource($candidate)) {
                return $candidate;
            }

            $candidate = $namespace.'\\'.$base;

            if (self::isPublishedResource($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether a resource collection keeps its source keys, making the payload a JSON object rather than an array.
     * Laravel honours the attribute and the property equally.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
     */
    public static function preservesKeys(ReflectionClass $collection): bool
    {
        $attribute = 'Illuminate\Http\Resources\Attributes\PreserveKeys';

        if (class_exists($attribute) && $collection->getAttributes($attribute) !== []) {
            return true;
        }

        return ($collection->getDefaultProperties()['preserveKeys'] ?? false) === true;
    }

    /**
     * A collected element type as the list the collection sends: `Record<string, R>` when it keeps its keys, else
     * `R[]`, through ValueResult::collectionListType(), the one spelling of a collected list.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
     */
    public static function listType(string $elementType, ReflectionClass $collection): string
    {
        return ValueResult::collectionListType($elementType, self::preservesKeys($collection));
    }

    /**
     * Whether a collection runs `ResourceCollection`'s own `toArray()`, which neither it nor an ancestor overrides.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
     */
    public static function runsFrameworkToArray(ReflectionClass $collection): bool
    {
        return $collection->hasMethod('toArray')
            && $collection->getMethod('toArray')->getDeclaringClass()->getName() === ResourceCollection::class;
    }

    /**
     * Whether a class's response re-indexes a numeric-keyed array into a list: an API resource that does not preserve
     * keys.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     */
    public static function reindexesNumericKeys(ReflectionClass $resource): bool
    {
        return $resource->isSubclassOf(JsonResource::class) && ! self::preservesKeys($resource);
    }

    /**
     * Whether a class is an API resource this run will also emit a file for.
     *
     * A convention-guessed candidate must be one, or the import it produces points at no module.
     *
     * @phpstan-assert-if-true class-string<JsonResource> $fqcn
     */
    private static function isPublishedResource(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true) && PublishedResourceRegistry::isPublished($fqcn);
    }
}
