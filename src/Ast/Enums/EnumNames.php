<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Enums;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use BackedEnum;

/**
 * The names an enum's tokens are spelled with: the const its own file exports, its `…Type` and a backed enum's `…Kind`.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class EnumNames
{
    /** @var array<string, string> enum FQCN => the name its own file exports it under, read once per run */
    private static array $constNames = [];

    /**
     * The name an enum's type is spelled with.
     */
    public static function typeName(string $fqcn): string
    {
        return self::constName($fqcn).'Type';
    }

    /**
     * The name a backed enum's case-name union is spelled with: its published const name and `Kind`.
     */
    public static function kindName(string $fqcn): string
    {
        return self::constName($fqcn).'Kind';
    }

    /**
     * The name an enum's const is spelled with, as the enum's own file exports it, whatever #[TsType] it carries.
     */
    public static function constName(string $fqcn): string
    {
        // On a memo hit too: each consumer's cache entry depends on the files the name is read from.
        DependencyRecorder::recordClass($fqcn);

        return self::$constNames[$fqcn] ??= TsNaming::enumName($fqcn);
    }

    /**
     * The names beside its type name that an enum's tokens are spelled with: a backed enum's `…Kind`.
     *
     * An import channel carries the enum behind each of these tokens too, so a queue reads them as the enum's own.
     *
     * @return list<string>
     */
    public static function siblingNames(string $fqcn): array
    {
        return enum_exists($fqcn) && is_a($fqcn, BackedEnum::class, true) ? [self::kindName($fqcn)] : [];
    }

    /**
     * Every name an enum's tokens are spelled with: its type name, then each sibling name.
     *
     * @return non-empty-list<string>
     */
    public static function names(string $fqcn): array
    {
        return [self::typeName($fqcn), ...self::siblingNames($fqcn)];
    }

    /**
     * Whether a result spells its enum's `…Type`, which a read of an enum `toTsType()` retypes does not.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function readsType(array $result): bool
    {
        return isset($result['directEnumFqcn'])
            && TsTypeString::typeNameOccursIn(self::typeName($result['directEnumFqcn']), $result['type']);
    }

    /**
     * Forget each enum's name, so a run reads every enum's `#[TsEnum]` afresh.
     */
    public static function forget(): void
    {
        self::$constNames = [];
    }
}
