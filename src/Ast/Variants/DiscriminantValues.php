<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumNames;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use UnitEnum;

/**
 * The finite set of values one analysis row can send, read off its published type arm by arm: a boolean, a scalar
 * literal, `null`, or each case of an enum the key reads directly through an alias that spells what PHP sends.
 *
 * @phpstan-import-type DiscriminantValueList from DiscriminantValue
 *
 * @internal
 */
final class DiscriminantValues
{
    /**
     * The values a row sends, in arm order and once each by `===`; null for a key the analysis does not hold, a row
     * with any arm that is not finite, or a key that wraps an enum, whose wrap sends an object however it prints.
     *
     * With `$scalarsMayBeCases`, as under AnalysisScope::$casesAsLiterals, a row's scalar literal may stand for an enum
     * case its read holds, so only `null` and the booleans answer, as they do for a key reading an enum directly.
     *
     * @return DiscriminantValueList|null
     */
    public static function of(MethodAnalysis $analysis, string $name, bool $scalarsMayBeCases): ?array
    {
        $row = array_find($analysis->writtenProperties(), static fn (array $property): bool => $property['name'] === $name);

        if ($row === null || $analysis->wrappedEnumFqcns($name) !== []) {
            return null;
        }

        $read = $analysis->readEnumFqcns($name);
        $enums = array_values(array_unique($read));
        $values = [];

        foreach (TsTypeString::splitTopLevelUnion(VisibleGaps::unmarked($row['type'])) as $arm) {
            $sent = self::armValues($arm, $enums, $scalarsMayBeCases || $read !== []);

            if ($sent === null) {
                return null;
            }

            array_push($values, ...$sent);
        }

        return self::once($values);
    }

    /**
     * The values one arm sends, or null for an arm that is not finite.
     *
     * @param  list<class-string>  $enums  the enums the key reads
     * @param  bool  $scalarsMayBeCases  whether a scalar literal may stand for a case the read holds
     * @return DiscriminantValueList|null
     */
    private static function armValues(string $arm, array $enums, bool $scalarsMayBeCases): ?array
    {
        if ($arm === 'boolean') {
            return [self::scalar(true), self::scalar(false)];
        }

        $literal = self::literal($arm);

        // An override, or a case read as what json_encode() writes, prints a scalar where the read holds the case.
        return match (true) {
            $literal === null => self::aliasCases($arm, $enums),
            $scalarsMayBeCases && ! is_bool($literal->value) && $literal->value !== null => null,
            default => [$literal],
        };
    }

    /**
     * The value a literal arm spells: a quoted string, or `true`, `false`, `null` or a number as PHP prints it back.
     */
    private static function literal(string $arm): ?DiscriminantValue
    {
        $string = PrintedKey::decodeQuoted($arm);

        if ($string !== null) {
            return self::scalar($string);
        }

        $decoded = json_decode($arm);
        $value = self::scalar(is_scalar($decoded) ? $decoded : null);

        // json_decode() reads unparsable text as null too, so only a value that prints back as the arm is its value.
        return $value->literal === $arm ? $value : null;
    }

    /**
     * Each case of the one enum whose alias an arm names, beside the scalar that alias spells it with; null unless
     * exactly one of the enums names it and that alias spells what PHP sends.
     *
     * @param  list<class-string>  $enums
     * @return DiscriminantValueList|null
     */
    private static function aliasCases(string $arm, array $enums): ?array
    {
        $named = array_values(array_filter(
            $enums,
            static fn (string $enum): bool => in_array($arm, EnumNames::names($enum), true),
        ));
        $enum = count($named) === 1 ? $named[0] : null;
        $literals = $enum === null ? null : EnumValueTypes::caseLiterals($enum, $arm);

        if ($literals === null || ! is_a($enum, UnitEnum::class, true)) {
            return null;
        }

        return array_map(
            static fn (UnitEnum $case): DiscriminantValue => new DiscriminantValue(
                $case,
                JsEmitter::toJsLiteral($literals[$case->name]),
            ),
            $enum::cases(),
        );
    }

    /**
     * The values with each repeat of an earlier one by `===` left out.
     *
     * @param  DiscriminantValueList  $values
     * @return DiscriminantValueList
     */
    private static function once(array $values): array
    {
        $kept = [];

        foreach ($values as $value) {
            if (! array_any($kept, static fn (DiscriminantValue $held): bool => $held->value === $value->value)) {
                $kept[] = $value;
            }
        }

        return $kept;
    }

    /**
     * A scalar or `null` beside its literal.
     */
    private static function scalar(bool|int|float|string|null $value): DiscriminantValue
    {
        return new DiscriminantValue($value, JsEmitter::toJsLiteral($value));
    }
}
