<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * How PHP stores an array key written as a string: as an int only when it is the canonical decimal spelling of one.
 *
 * @internal
 */
final class PhpArrayKey
{
    /**
     * The int PHP stores this key as, such as `'6'`, or null for a key it keeps a string, such as `'06'`.
     */
    public static function storedInt(string $key): ?int
    {
        return (string) (int) $key === $key ? (int) $key : null;
    }
}
