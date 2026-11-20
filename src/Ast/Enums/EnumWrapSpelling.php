<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Enums;

use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\AsEnumSpelling;
use Illuminate\Support\Facades\Config;

/**
 * How an `EnumResource` wrap of an enum publishes: as `AsEnum<typeof Const>` while the tolki package is on.
 *
 * @internal
 */
final class EnumWrapSpelling
{
    /** A wrap of any const, unanchored and undelimited, capturing its name as `const`. */
    public const string PATTERN = AsEnumSpelling::PATTERN;

    /**
     * Whether a wrap publishes as `AsEnum`, which only the tolki package's types spell.
     */
    public static function enabled(): bool
    {
        return Config::boolean('ts-publish.enums.use_tolki_package');
    }

    /**
     * The `AsEnum` type of a const, as a list when $list.
     */
    public static function of(string $const, bool $list = false): string
    {
        return AsEnumSpelling::of($const, $list);
    }

    /**
     * The `AsEnum` type of an enum, by the const its own file exports, as a list when $list.
     */
    public static function ofEnum(string $enum, bool $list = false): string
    {
        return self::of(ValueResult::enumConstName($enum), $list);
    }
}
