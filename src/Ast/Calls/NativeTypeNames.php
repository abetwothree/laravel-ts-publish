<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use ReflectionType;

/**
 * The names a native type spells, which the call checks match against keywords such as `callable` and `closure`.
 *
 * @internal
 */
final class NativeTypeNames
{
    /**
     * The lowercased names a native type spells through its unions, intersections and `?`; none for no type.
     *
     * @return list<string>
     */
    public static function of(?ReflectionType $type): array
    {
        return preg_split('/[|&()?\s]+/', strtolower((string) $type), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
