<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedResourceRegistry;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;

/**
 * A resource collection's facts, read off the class Laravel instantiates: the API resource it collects, whether it
 * keeps its source keys, the list its items publish as, and whether its body is Laravel's own. Static so the
 * publisher's re-index can ask of a class it never analyzes.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type CollectedElement = array{fqcn: class-string<JsonResource|Model>, model: bool}
 *
 * @internal
 */
final class ResourceCollections
{
    /**
     * Resolve the API resource a ResourceCollection collects, in Laravel's `collects()` order: #[Collects], the
     * `$collects` default, then the bare `Foo` and the `FooResource` its name implies. The first class found decides;
     * one that is no API resource yields null, as Laravel throws; only a convention guess must also be published.
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
     * What each item a runtime class collects publishes as: the API resource its collects() picks, else, when that
     * finds nothing, the raw models the nearest ancestor collection's resource wraps, as that resource's backing model
     * when it is published, else as the resource itself.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $runtime
     * @return CollectedElement|null
     */
    public static function collectedElement(ReflectionClass $runtime): ?array
    {
        $resource = self::collectedResource($runtime->getName());

        if ($resource !== null) {
            return ['fqcn' => $resource, 'model' => false];
        }

        // Laravel throws for a pick that is no API resource and maps each item into an unpublished one: either way,
        // the class's own pick decides, never an ancestor's.
        if (self::namedCollectedClass($runtime) !== null || self::conventionalCollectedClass($runtime) !== null) {
            return null;
        }

        return self::ancestorElement($runtime);
    }

    /**
     * A collected element as the value one item publishes: its type and the import channel it travels on; `unknown`
     * for none.
     *
     * @param  CollectedElement|null  $element
     * @return ValueExpressionResult
     */
    public static function elementValue(?array $element): array
    {
        if ($element === null) {
            return ValueResult::unknown();
        }

        return [
            ...ValueResult::unknown(),
            'type' => TsNaming::resourceTypeName($element['fqcn']),
            ...($element['model'] ? ['modelFqcn' => $element['fqcn']] : ['resourceFqcn' => $element['fqcn']]),
        ];
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
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
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
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
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
     * The element of a collection whose collects() finds nothing, from the nearest ancestor collection naming an API
     * resource: Laravel then sends the raw models that resource would wrap, which its backing model types best.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
     * @return CollectedElement|null
     */
    private static function ancestorElement(ReflectionClass $collection): ?array
    {
        for ($ancestor = $collection->getParentClass(); $ancestor !== false; $ancestor = $ancestor->getParentClass()) {
            if (! $ancestor->isSubclassOf(ResourceCollection::class)) {
                return null;
            }

            $resource = self::collectedResource($ancestor->getName());

            if ($resource === null) {
                continue;
            }

            // The resource's docblock or attribute names the model, so the collection's type follows its file.
            DependencyRecorder::recordClass($resource);
            $model = resolve(ModelClassResolver::class)->resolve(new ReflectionClass($resource));

            return $model !== null && PublishedClasses::exports($model)
                ? ['fqcn' => $model, 'model' => true]
                : ['fqcn' => $resource, 'model' => false];
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
