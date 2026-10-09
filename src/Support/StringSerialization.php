<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Answers whether `json_encode()` writes an instance of a class as a string.
 *
 * `json_encode()` reads only `jsonSerialize()`, never `__toString()`, so this is the one rule `toTsType()` and a `new`
 * parameter default share.
 *
 * @internal
 */
final class StringSerialization
{
    /**
     * The type `json_encode()` writes an instance of a class or interface as, when that is a string: `string`, or
     * `string | null` for a `?string` `jsonSerialize()`; null for anything else.
     *
     * Carbon's own `jsonSerialize()` declares `mixed` but writes its ISO string, so a date that keeps it is `string`.
     */
    public static function jsonStringType(string $class): ?string
    {
        // Before the guards: a class or interface that later gains a string jsonSerialize() changes the answer.
        DependencyRecorder::recordClass($class);

        if ((! class_exists($class) && ! interface_exists($class))
            || ! is_a($class, JsonSerializable::class, true)
            || is_a($class, Model::class, true)) {
            return null;
        }

        $method = new ReflectionMethod($class, 'jsonSerialize');
        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType && $type->getName() === 'string') {
            return $type->allowsNull() ? 'string | null' : 'string';
        }

        return is_a($class, DateTimeInterface::class, true) && str_starts_with($method->getDeclaringClass()->getName(), 'Carbon\\')
            ? 'string'
            : null;
    }
}
