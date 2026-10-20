<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
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
 * @phpstan-type CollectedElement = array{fqcn: class-string<JsonResource>, model: false}|array{fqcn: class-string<Model>, model: true}
 *
 * @internal
 */
final class ResourceCollections
{
    /**
     * What each item a runtime class collects publishes as: its own pick in Laravel's `collects()` order, null for one
     * that is no API resource, as Laravel throws, or an unpublished convention guess; with no pick, the raw models the
     * nearest ancestor's pick wraps.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $runtime
     * @return CollectedElement|null
     */
    public static function collectedElement(ReflectionClass $runtime): ?array
    {
        $named = self::namedCollectedClass($runtime);
        $collected = $named ?? self::conventionalCollectedClass($runtime);

        if ($collected === null) {
            return self::ancestorElement($runtime);
        }

        if (! is_a($collected, JsonResource::class, true)) {
            return self::declineCollected($runtime->getName(), $collected);
        }

        return $named !== null || PublishedResourceRegistry::isPublished($collected)
            ? ['fqcn' => $collected, 'model' => false]
            : null;
    }

    /**
     * Whether a runtime class's collects() picks no class, so Laravel keeps the raw items it was given, rather than
     * mapping them into an API resource or throwing for a class that is not one.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $runtime
     */
    public static function keepsRawItems(ReflectionClass $runtime): bool
    {
        return self::pick($runtime) === null;
    }

    /**
     * A collected element as the value one item publishes: its type, a JSON:API resource's under `data` as its
     * resolve() sends it, and the import channel it travels on; `unknown` for none.
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
            'type' => $element['model'] ? TsNaming::resourceTypeName($element['fqcn']) : JsonApiResources::instanceType($element['fqcn']),
            ...($element['model'] ? ['modelFqcn' => $element['fqcn']] : ['resourceFqcn' => $element['fqcn']]),
        ];
    }

    /**
     * A collected element's list as a value: `R[]`, or `Record<string, R>` when the collection keeps its keys, on R's
     * import channel; `unknown` items for none, still the list Laravel sends of them.
     *
     * @param  CollectedElement|null  $element
     * @return ValueExpressionResult
     */
    public static function listValue(?array $element, bool $preservesKeys): array
    {
        $item = self::elementValue($element);

        return [...$item, 'type' => ValueResult::collectionListType($item['type'], $preservesKeys)];
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
     * The element of a collection whose collects() finds nothing, from the nearest ancestor collection with a pick:
     * Laravel then sends the raw models that pick would wrap, which its published backing model types best, else the
     * pick itself while the run publishes it. A pick that is no API resource names nothing.
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

            $pick = self::pick($ancestor);

            if ($pick === null) {
                continue;
            }

            if (! is_a($pick, JsonResource::class, true)) {
                return null;
            }

            // The resource's docblock or attribute names the model, so the collection's type follows its file.
            DependencyRecorder::recordClass($pick);
            $model = resolve(ModelClassResolver::class)->resolve(new ReflectionClass($pick));

            return match (true) {
                $model !== null && PublishedClasses::exports($model) => ['fqcn' => $model, 'model' => true],
                PublishedClasses::exports($pick) => ['fqcn' => $pick, 'model' => false],
                default => null,
            };
        }

        return null;
    }

    /**
     * The class a collection's `collects()` would pick, unchecked: named, else by convention.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
     */
    private static function pick(ReflectionClass $collection): ?string
    {
        return self::namedCollectedClass($collection) ?? self::conventionalCollectedClass($collection);
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
