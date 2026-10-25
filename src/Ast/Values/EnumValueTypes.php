<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use BackedEnum;
use Closure;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * What an enum's `->value` and `->name` publish: the published enum's alias with its import where that alias spells
 * what PHP sends, otherwise the literal union of what PHP sends; the rules are in docs/components/receiver-types.md.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class EnumValueTypes
{
    /** Past this magnitude JavaScript reads an integer literal as a neighbouring number. */
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

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

        $reflection = self::casedEnum($enum);

        if ($reflection === null || ! $reflection->isBacked()) {
            return null;
        }

        $values = self::caseScalars(
            $reflection,
            fn (ReflectionEnumUnitCase $case): int|string => JsEmitter::enumScalar($case->getValue()),
        );

        if (array_any($values, fn (int|string $value): bool => is_int($value) && abs($value) > self::MAX_SAFE_INTEGER)) {
            return self::typed('number');
        }

        return self::aliasUsable($reflection, $carriesImports, fromNames: false)
            ? self::alias($reflection, kind: false)
            : self::typed(self::literals($values));
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

        $reflection = self::casedEnum($enum);

        if ($reflection === null) {
            return null;
        }

        // A pure enum publishes its names as its values, so its `…Type` is the name union; a backed one has a `…Kind`.
        $backed = $reflection->isBacked();

        if (self::aliasUsable($reflection, $carriesImports, fromNames: $backed)) {
            return self::alias($reflection, kind: $backed);
        }

        return self::typed(self::literals(
            self::caseScalars($reflection, fn (ReflectionEnumUnitCase $case): string => $case->getName()),
        ));
    }

    /**
     * The enum's reflection, or null when the class is no enum or declares no case.
     *
     * @return ReflectionEnum<UnitEnum>|null
     */
    private static function casedEnum(string $enum): ?ReflectionEnum
    {
        if (! enum_exists($enum)) {
            return null;
        }

        $reflection = new ReflectionEnum($enum);

        return $reflection->getCases() === [] ? null : $reflection;
    }

    /**
     * Whether the enum's alias spells what PHP sends: the reader carries imports, `ts:publish` writes the enum, and no
     * case's #[TsCase] replaces the scalar the alias is built from, its published name `$fromNames`, else its value.
     *
     * @param  ReflectionEnum<UnitEnum>  $reflection
     */
    private static function aliasUsable(ReflectionEnum $reflection, bool $carriesImports, bool $fromNames): bool
    {
        return $carriesImports
            && PublishedEnums::includes($reflection->getName())
            && ! array_any($reflection->getCases(), function (ReflectionEnumUnitCase $case) use ($fromNames): bool {
                $tsCase = ($case->getAttributes(TsCase::class)[0] ?? null)?->newInstance();

                return $tsCase !== null && ($fromNames ? $tsCase->name : $tsCase->value) !== '';
            });
    }

    /**
     * The enum's published `…Type` alias, or with `$kind` its `…Kind`, carrying the enum so its file imports it.
     *
     * @param  ReflectionEnum<UnitEnum>  $reflection
     * @return ValueExpressionResult
     */
    private static function alias(ReflectionEnum $reflection, bool $kind): array
    {
        $enum = $reflection->getName();

        return [...self::typed($kind ? ValueResult::enumKindName($enum) : ValueResult::enumTypeName($enum)), 'directEnumFqcn' => $enum];
    }

    /**
     * One scalar read off each of the enum's cases, in case order.
     *
     * @param  ReflectionEnum<UnitEnum>  $reflection
     * @param  Closure(ReflectionEnumUnitCase): (int|string)  $read
     * @return list<int|string>
     */
    private static function caseScalars(ReflectionEnum $reflection, Closure $read): array
    {
        return array_values(array_map($read, $reflection->getCases()));
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
