<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use ReflectionClass;

/**
 * Whether a resource preserves its keys, read by the resource analyzer and the handlers typing a resource collection.
 * `collectionPreservesKeys()` is static so the publisher's re-index can ask it of a class it never analyzes.
 *
 * @internal
 */
trait ChecksPreserveKeys
{
    /**
     * Whether a resource collection keeps its source keys, making the payload a JSON object rather
     * than an array. Laravel honours the attribute and the property equally.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     */
    protected static function collectionPreservesKeys(ReflectionClass $reflection): bool
    {
        $attribute = 'Illuminate\Http\Resources\Attributes\PreserveKeys';

        if (class_exists($attribute) && $reflection->getAttributes($attribute) !== []) {
            return true;
        }

        return ($reflection->getDefaultProperties()['preserveKeys'] ?? false) === true;
    }

    /**
     * Wrap a collected element type as `Record<string, R>` when the reflected class preserves its
     * keys, or as `R[]` otherwise — the single point every collection-typing call site shares.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     */
    protected function wrapCollectionElementType(string $elementType, ReflectionClass $reflection): string
    {
        return self::collectionPreservesKeys($reflection)
            ? "Record<string, {$elementType}>"
            : $elementType.'[]';
    }
}
