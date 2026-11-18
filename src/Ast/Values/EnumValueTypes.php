<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Collectors\PublishedEnums;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\RedundantArms;
use BackedEnum;
use Closure;
use JsonSerializable;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use UnitEnum;

/**
 * What an enum's `->value` and `->name` publish: the published enum's alias with its import where that alias spells
 * what PHP sends, otherwise the literal union of what PHP sends, and one case's own as its literal; the rules are in
 * docs/components/receiver-types.md.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type AliasLiterals from RedundantArms
 * @phpstan-import-type TokenSpelling from ClassTokenQueue
 *
 * @phpstan-type EnumCases = array{
 *     enum: class-string,
 *     backed: bool,
 *     names: list<string>,
 *     values: list<int|string>,
 *     renamed: bool,
 *     revalued: bool,
 * }
 * @phpstan-type AbsorbRules = array{AliasLiterals, bool}
 *
 * @internal
 */
final class EnumValueTypes
{
    /** Past this magnitude JavaScript reads an integer literal as a neighbouring number. */
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    /** @var array<string, EnumCases|null> enum => what its cases spell, read once per process; null: no enum or case */
    private static array $cases = [];

    /**
     * What reading `->value` or `->name` on the enum publishes, as value() and name() spell it; null for any other
     * member, or a class that is no enum.
     *
     * @return ValueExpressionResult|null
     */
    public static function member(string $enum, string $member, bool $carriesImports): ?array
    {
        if (! is_a($enum, UnitEnum::class, true)) {
            return null;
        }

        return match ($member) {
            'value' => self::value($enum, $carriesImports),
            'name' => self::name($enum, $carriesImports),
            default => null,
        };
    }

    /**
     * What reading `->value` on the enum publishes, or null when it holds none: a pure enum, one with no case, or a
     * class that is no enum.
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

        if (array_any($cases['values'], self::unsafeInteger(...))) {
            return self::typed('number');
        }

        return self::aliasUsable($cases, $carriesImports, fromNames: false)
            ? self::alias($cases['enum'], kind: false)
            : self::typed(self::literals($cases['values']));
    }

    /**
     * What reading `->name` on the enum publishes, or null when it has no case to read, or is no enum.
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
     * What json_encode() writes one case as, spelled with no import: a backed case's value as a literal, or `number`
     * past the safe integer range; null for a pure enum's case, which json_encode() cannot write.
     *
     * @return ValueExpressionResult|null
     */
    public static function caseValue(UnitEnum $case): ?array
    {
        DependencyRecorder::recordClass($case::class);

        // json_encode() sends a JsonSerializable case's jsonSerialize(), not its value.
        if (! $case instanceof BackedEnum || $case instanceof JsonSerializable) {
            return null;
        }

        return self::typed(self::scalarLiteral($case->value));
    }

    /**
     * What reading `->value` or `->name` on one case sends, spelled with no import: its value as a literal, or `number`
     * past the safe integer range, or its name; null for a pure case's value or any other member.
     *
     * @return ValueExpressionResult|null
     */
    public static function caseMember(UnitEnum $case, string $member): ?array
    {
        DependencyRecorder::recordClass($case::class);

        return match (true) {
            $member === 'name' => self::typed(self::literals([$case->name])),
            $member === 'value' && $case instanceof BackedEnum => self::typed(self::scalarLiteral($case->value)),
            default => null,
        };
    }

    /**
     * Each usable alias of the enums a key or value reads beside the case scalars it admits. Enums sharing an alias
     * name admit under it only what all of them admit; an enum in `$wrapped` admits nothing under its names, and a
     * name in `$takenNames`, which another class on the key prints, is left out.
     *
     * @param  list<class-string>  $read  the enums it reads directly
     * @param  list<class-string>  $wrapped  the enums it wraps in an `EnumResource`
     * @param  list<string>  $takenNames
     * @return AliasLiterals
     */
    public static function aliasLiterals(array $read, array $wrapped = [], array $takenNames = []): array
    {
        if ($read === []) {
            return [];
        }

        $admitted = [];
        $usable = [];

        foreach (array_unique([...$read, ...$wrapped]) as $enum) {
            $own = in_array($enum, $wrapped, true) ? [] : self::ownAliasLiterals($enum);

            // A name this enum spells but cannot use admits nothing, since a token of that name may be this enum's.
            foreach (ValueResult::enumNames($enum) as $alias) {
                $scalars = $own[$alias] ?? [];
                $admitted[$alias] = array_key_exists($alias, $admitted)
                    ? self::sharedScalars($admitted[$alias], $scalars)
                    : $scalars;
            }

            $usable += array_fill_keys(array_keys($own), true);
        }

        return array_diff_key(array_intersect_key($admitted, $usable), array_flip($takenNames));
    }

    /**
     * A key's rules for leaving out redundant arms: aliasLiterals() over its enums, and whether two arms spelled alike
     * are one, which holds while it keeps no token queue, no name spells two of its classes and none is queued later.
     *
     * @param  list<class-string>  $read  the enums it reads directly
     * @param  list<class-string>  $wrapped  the enums it wraps in an `EnumResource`
     * @param  list<string>  $takenNames  the names its models and resources print
     * @param  list<TokenSpelling>  $classes  its models and resources, beside the names they print
     * @param  (Closure(): bool)|null  $queuesLater  whether a class is queued on it after this pass
     * @return AbsorbRules
     */
    public static function absorbRules(
        array $read,
        array $wrapped,
        array $takenNames,
        array $classes,
        bool $keepsTokens,
        ?Closure $queuesLater = null,
    ): array {
        return [
            self::aliasLiterals($read, $wrapped, $takenNames),
            ! $keepsTokens
                && ! self::namesTwoClasses([...$read, ...$wrapped], $classes)
                && ! ($queuesLater !== null && $queuesLater()),
        ];
    }

    /**
     * Whether one name spells two different classes among these enums, each by its const and every alias name, and the
     * other classes beside the names they print: two arms of a key spelled alike may then hold two classes.
     *
     * @param  list<class-string>  $enums
     * @param  list<TokenSpelling>  $classes
     */
    public static function namesTwoClasses(array $enums, array $classes): bool
    {
        $spellings = $classes;

        foreach (array_unique($enums) as $enum) {
            foreach ([ValueResult::enumConstName($enum), ...ValueResult::enumNames($enum)] as $name) {
                $spellings[] = [$enum, $name];
            }
        }

        /** @var array<string, class-string> $firstOf name => the first class seen under it */
        $firstOf = [];

        foreach ($spellings as [$fqcn, $name]) {
            if (($firstOf[$name] ??= $fqcn) !== $fqcn) {
                return true;
            }
        }

        return false;
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
     * One enum's aliases that spell what PHP sends, beside the scalars each admits, as aliasUsable() rules for a reader
     * carrying imports; none past the safe integer range, and none the enum's own override is spelled like.
     *
     * @return AliasLiterals
     */
    private static function ownAliasLiterals(string $enum): array
    {
        $cases = self::cases($enum);

        if ($cases === null || array_any($cases['values'], self::unsafeInteger(...))) {
            return [];
        }

        $literals = [];

        if (self::aliasUsable($cases, carriesImports: true, fromNames: false)) {
            $literals[ValueResult::enumTypeName($enum)] = $cases['backed'] ? $cases['values'] : $cases['names'];
        }

        if ($cases['backed'] && self::aliasUsable($cases, carriesImports: true, fromNames: true)) {
            $literals[ValueResult::enumKindName($enum)] = $cases['names'];
        }

        $override = LaravelTsPublish::retypedEnumType($enum);

        return $override === null ? $literals : array_filter(
            $literals,
            static fn (string $alias): bool => ! TsTypeString::typeNameOccursIn($alias, $override),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The scalars of the first list the second also holds, in order.
     *
     * @param  list<int|string>  $scalars
     * @param  list<int|string>  $others
     * @return list<int|string>
     */
    private static function sharedScalars(array $scalars, array $others): array
    {
        return array_values(array_filter(
            $scalars,
            static fn (int|string $scalar): bool => in_array($scalar, $others, true),
        ));
    }

    /**
     * The enum's published `…Type` alias, or with `$kind` its `…Kind`, carrying the enum so its file imports it, and
     * marked as the scalar a read sends rather than the case.
     *
     * @param  class-string  $enum
     * @return ValueExpressionResult
     */
    private static function alias(string $enum, bool $kind): array
    {
        return [
            ...self::typed($kind ? ValueResult::enumKindName($enum) : ValueResult::enumTypeName($enum)),
            'directEnumFqcn' => $enum,
            'sendsEnumScalar' => true,
        ];
    }

    /**
     * One case's scalar as a TypeScript literal, or `number` past the safe integer range.
     */
    private static function scalarLiteral(int|string $value): string
    {
        return self::unsafeInteger($value) ? 'number' : self::literals([$value]);
    }

    /**
     * Whether a case scalar is an integer JavaScript reads as a neighbouring number.
     */
    private static function unsafeInteger(int|string $value): bool
    {
        return is_int($value) && abs($value) > self::MAX_SAFE_INTEGER;
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
