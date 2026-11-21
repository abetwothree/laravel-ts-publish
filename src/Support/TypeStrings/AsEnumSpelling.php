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
    /**
     * The `AsEnum` type of a const, as a list when $list.
     */
    public static function of(string $const, bool $list = false): string
    {
        return 'AsEnum<typeof '.$const.'>'.($list ? '[]' : '');
    }

    /**
     * The pattern of a wrap of this one const, capturing it as `const`, undelimited for a `/` delimiter.
     */
    public static function pattern(string $const): string
    {
        return 'AsEnum<\s*typeof\s+(?<const>'.preg_quote($const, '/').')\s*>';
    }
}
