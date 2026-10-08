<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Ast\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Answers whether `json_encode()` writes an instance of a class as a string, and whether a class `toTsType()` publishes
 * as `string` reaches JSON as something else.
 *
 * `json_encode()` reads only `jsonSerialize()`, never `__toString()`, and writes a plain `DateTime` as a
 * `{date, timezone_type, timezone}` object, though `toTsType()`'s date map types it as a date.
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

    /**
     * Whether a method's native return type or `@return` docblock names a class published as a false `string`.
     *
     * Every class the declaration spells counts, a list element such as `list<CarbonInterval>` included.
     */
    public static function methodReturnsFalseString(string $class, string $methodName): bool
    {
        if (! method_exists($class, $methodName)) {
            return false;
        }

        foreach (self::declaredReturnClassNames(new ReflectionMethod($class, $methodName), $class) as $name) {
            if (self::isFalseString($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether `toTsType()` publishes a class as `string` while `json_encode()` emits no string for it.
     */
    public static function isFalseString(string $class): bool
    {
        if (! class_exists($class) || is_a($class, Model::class, true)) {
            return false;
        }

        $jsonSerializable = is_a($class, JsonSerializable::class, true);

        // Carbon's jsonSerialize() is its ISO string; a DateTime without one serializes as an object.
        if (is_a($class, DateTimeInterface::class, true)) {
            return ! $jsonSerializable;
        }

        if (is_a($class, Arrayable::class, true) || ! method_exists($class, '__toString')) {
            return false;
        }

        if (! $jsonSerializable) {
            return true;
        }

        $serialized = new ReflectionMethod($class, 'jsonSerialize')->getReturnType();

        return ! ($serialized instanceof ReflectionNamedType && $serialized->getName() === 'string');
    }

    /**
     * Every class name a method's native return and `@return` docblock spell, with `self`/`static`/`$this` resolved.
     *
     * @return list<string>
     */
    private static function declaredReturnClassNames(ReflectionMethod $method, string $receiverClass): array
    {
        $declaring = $method->getDeclaringClass()->getName();
        $native = $method->getReturnType();
        $names = [];

        $arms = match (true) {
            $native instanceof ReflectionUnionType, $native instanceof ReflectionIntersectionType => $native->getTypes(),
            $native === null => [],
            default => [$native],
        };

        foreach ($arms as $arm) {
            // A DNF arm is an intersection nested inside a union: flatten one level to reach its names.
            foreach ($arm instanceof ReflectionIntersectionType ? $arm->getTypes() : [$arm] as $named) {
                if ($named instanceof ReflectionNamedType && (! $named->isBuiltin() || $named->getName() === 'static')) {
                    $names[] = $named->getName();
                }
            }
        }

        $docblock = resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $method->getDocComment());

        if ($docblock !== null && preg_match_all('/\$this|[\\\\A-Z][\\\\\w]*|\bs(?:elf|tatic)\b/', $docblock, $matches) > 0) {
            $context = LaravelTsPublish::methodDeclaringFileClass($method);
            $useMap = LaravelTsPublish::parseFileUseStatements($context);

            foreach ($matches[0] as $token) {
                $names[] = in_array($token, ['$this', 'static', 'self'], true)
                    ? $token
                    : LaravelTsPublish::resolveDocblockTypeName($token, $useMap, $context->getNamespaceName());
            }
        }

        return array_map(fn (string $name): string => match ($name) {
            '$this', 'static' => $receiverClass,
            'self' => $declaring,
            default => $name,
        }, $names);
    }
}
