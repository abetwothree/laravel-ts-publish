<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

/**
 * The tolki package's `AsEnum<typeof Const>` type, written and read: the one place a type string spells it.
 *
 * @internal
 */
final class AsEnumSpelling
{
    /** A wrap of any const, unanchored and undelimited, capturing its name as `const`. */
    public const string PATTERN = 'AsEnum<\s*typeof\s+(?<const>'.self::CONST_NAME.')\s*>';

    /** A const's name, or the namespace-qualified one the globals file spells. */
    private const string CONST_NAME = '[A-Za-z_$][A-Za-z0-9_$]*(?:\.[A-Za-z_$][A-Za-z0-9_$]*)*';

    /**
     * The `AsEnum` type of a const, as a list when $list.
     */
    public static function of(string $const, bool $list = false): string
    {
        return 'AsEnum<typeof '.$const.'>'.($list ? '[]' : '');
    }

    /**
     * The pattern of a wrap of this one const, or PATTERN when null, undelimited for a `/` delimiter.
     */
    public static function pattern(?string $const = null): string
    {
        return $const === null ? self::PATTERN : 'AsEnum<\s*typeof\s+(?<const>'.preg_quote($const, '/').')\s*>';
    }
}
