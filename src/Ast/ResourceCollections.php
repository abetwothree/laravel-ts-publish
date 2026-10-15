<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
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
     * Resolve the API resource a ResourceCollection collects, in the order Laravel's `collects()` reads: the
     * #[Collects] attribute, the `$collects` default, then the bare `Foo` and the `FooResource` its name implies.
     *
     * The first class found decides, and one that is no API resource yields null, since Laravel throws rather than
     * read on. Only a convention guess must also be published.
     *
     * @param  class-string  $collectionFqcn
     * @return class-string<JsonResource>|null
     */
    public static function collectedResource(string $collectionFqcn): ?string
    {
        $reflection = new ReflectionClass($collectionFqcn);
        $named = self::namedCollectedClass($reflection);
        $collected = $named ?? self::conventionalCollectedClass($reflection);

        if ($collected === null) {
            return null;
        }

        if (! is_a($collected, JsonResource::class, true)) {
            return self::declineCollected($collectionFqcn, $collected);
        }

        return $named !== null || PublishedResourceRegistry::isPublished($collected) ? $collected : null;
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
    public static function isPublishedResource(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true) && PublishedResourceRegistry::isPublished($fqcn);
    }

    /**
     * The class a collection names by its #[Collects] attribute (Laravel 13) or its `$collects` default, or null.
     *
     * @param  ReflectionClass<object>  $collection
     */
    private static function namedCollectedClass(ReflectionClass $collection): ?string
    {
        $collectsAttribute = 'Illuminate\Http\Resources\Attributes\Collects';

        if (class_exists($collectsAttribute)) {
            $attributes = $collection->getAttributes($collectsAttribute);

            if ($attributes !== []) {
                return $attributes[0]->newInstance()->class;
            }
        }

        $collects = $collection->getDefaultProperties()['collects'] ?? null;

        return is_string($collects) && $collects !== '' ? $collects : null;
    }

    /**
     * The class Laravel's naming convention picks for a `FooCollection`: `Foo` when it exists, else `FooResource`.
     *
     * @param  ReflectionClass<object>  $collection
     */
    private static function conventionalCollectedClass(ReflectionClass $collection): ?string
    {
        $name = $collection->getName();

        if (! str_ends_with($name, 'Collection')) {
            return null;
        }

        $base = substr($name, 0, -strlen('Collection'));

        foreach ([$base, $base.'Resource'] as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Warn that a collection collects a class Laravel refuses to, and collect nothing.
     */
    private static function declineCollected(string $collectionFqcn, string $collected): null
    {
        AnalysisWarnings::addOnce($collectionFqcn, sprintf(
            'The collected class %s is not an API resource, so Laravel throws a LogicException; collect an API resource.',
            $collected,
        ));

        return null;
    }
}
