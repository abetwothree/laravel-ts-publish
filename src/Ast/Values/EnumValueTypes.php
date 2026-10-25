<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use BackedEnum;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * What an enum's `->value` and `->name` publish: the published enum's alias with its import where that alias spells
 * what PHP sends, otherwise the literal union of what PHP sends; the rules are in docs/components/receiver-types.md.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @phpstan-type EnumCases = array{
 *     enum: class-string,
 *     backed: bool,
 *     names: list<string>,
 *     values: list<int|string>,
 *     renamed: bool,
 *     revalued: bool,
 * }
 *
 * @internal
 */
final class EnumValueTypes
{
    /** Past this magnitude JavaScript reads an integer literal as a neighbouring number. */
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    /** @var array<string, EnumCases|null> enum => what its cases spell, read once per process; null with no case */
    private static array $cases = [];

    /**
     * What reading `->value` on the enum publishes, or null when it holds none: a pure enum, or one with no case.
     *
     * @return ValueExpressionResult|null
     */
    public static function value(string $enum, bool $carriesImports): ?array
    {
        DependencyRecorder::recordClass($enum);

        if ($enum === BackedEnum::class) {
            return self::typed('string | number');
        }

        $cases = self::cases($enum);

        if ($cases === null || ! $cases['backed']) {
            return null;
        }

        if (array_any($cases['values'], fn (int|string $value): bool => is_int($value) && abs($value) > self::MAX_SAFE_INTEGER)) {
            return self::typed('number');
        }

        return self::aliasUsable($cases, $carriesImports, fromNames: false)
            ? self::alias($cases['enum'], kind: false)
            : self::typed(self::literals($cases['values']));
    }

    /**
     * What reading `->name` on the enum publishes, or null when it has no case to read.
     *
     * @return ValueExpressionResult|null
     */
    public static function name(string $enum, bool $carriesImports): ?array
    {
        DependencyRecorder::recordClass($enum);

        if ($enum === UnitEnum::class || $enum === BackedEnum::class) {
            return self::typed('string');
        }

        $cases = self::cases($enum);

        if ($cases === null) {
            return null;
        }

        // A pure enum publishes its names as its values, so its `…Type` is the name union; a backed one has a `…Kind`.
        return self::aliasUsable($cases, $carriesImports, fromNames: $cases['backed'])
            ? self::alias($cases['enum'], kind: $cases['backed'])
            : self::typed(self::literals($cases['names']));
    }

    /**
     * The literal union of the enum's case names, in case order: what its `->name` sends, spelled with no import.
     */
    public static function nameUnion(string $enum): string
    {
        $cases = self::cases($enum);

        if ($cases === null) {
            return 'string'; // @codeCoverageIgnore
        }

        return self::literals($cases['names']);
    }

    /**
     * What the enum's cases spell, read once per process: a class's cases never change while it runs. Null when the
     * class is no enum or declares no case.
     *
     * @return EnumCases|null
     */
    private static function cases(string $enum): ?array
    {
        if (! array_key_exists($enum, self::$cases)) {
            self::$cases[$enum] = self::readCases($enum);
        }

        return self::$cases[$enum];
    }

    /**
     * Read the enum's case names, its case scalars when it is backed, and whether a case's #[TsCase] replaces either.
     *
     * @return EnumCases|null
     */
    private static function readCases(string $enum): ?array
    {
        if (! enum_exists($enum)) {
            return null;
        }

        $reflection = new ReflectionEnum($enum);
        $cases = $reflection->getCases();

        if ($cases === []) {
            return null;
        }

        $renamed = false;
        $revalued = false;

        foreach ($cases as $case) {
            $tsCase = ($case->getAttributes(TsCase::class)[0] ?? null)?->newInstance();
            $renamed = $renamed || ($tsCase !== null && $tsCase->name !== '');
            $revalued = $revalued || ($tsCase !== null && $tsCase->value !== '');
        }

        return [
            'enum' => $reflection->getName(),
            'backed' => $reflection->isBacked(),
            'names' => array_values(array_map(fn (ReflectionEnumUnitCase $case): string => $case->getName(), $cases)),
            'values' => $reflection->isBacked()
                ? array_values(array_map(
                    fn (ReflectionEnumUnitCase $case): int|string => JsEmitter::enumScalar($case->getValue()),
                    $cases,
                ))
                : [],
            'renamed' => $renamed,
            'revalued' => $revalued,
        ];
    }

    /**
     * Whether the enum's alias spells what PHP sends: the reader carries imports, `ts:publish` writes the enum, and no
     * case's #[TsCase] replaces the scalar the alias is built from, with `$fromNames` its published name, else its
     * value.
     *
     * @param  EnumCases  $cases
     */
    private static function aliasUsable(array $cases, bool $carriesImports, bool $fromNames): bool
    {
        return $carriesImports
            && PublishedEnums::includes($cases['enum'])
            && ! ($fromNames ? $cases['renamed'] : $cases['revalued']);
    }

    /**
     * The enum's published `…Type` alias, or with `$kind` its `…Kind`, carrying the enum so its file imports it.
     *
     * @param  class-string  $enum
     * @return ValueExpressionResult
     */
    private static function alias(string $enum, bool $kind): array
    {
        return [...self::typed($kind ? ValueResult::enumKindName($enum) : ValueResult::enumTypeName($enum)), 'directEnumFqcn' => $enum];
    }

    /**
     * The values as a union of TypeScript literals, in case order.
     *
     * @param  list<int|string>  $values
     */
    private static function literals(array $values): string
    {
        return implode(' | ', array_map(fn (int|string $value): string => JsEmitter::toJsLiteral($value), $values));
    }

    /**
     * A result that is the type alone.
     *
     * @return ValueExpressionResult
     */
    private static function typed(string $type): array
    {
        return ['type' => $type, 'optional' => false];
    }
}
