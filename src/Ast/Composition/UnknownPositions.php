<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;

/**
 * Fills the `unknown` positions a type holds, a whole value, an object member, a list element or a record value, from a
 * declared type at the same position; every position the type itself types stays as it is.
 *
 * @internal
 */
final class UnknownPositions
{
    /**
     * $type with each position it holds as `unknown` taken from $declared at that position where $fills admits it. An
     * arm takes the one arm of $declared, `null` aside, of its own kind: an object, a `T[]` list or a Record.
     *
     * @param  Closure(string): bool  $fills
     */
    public static function filled(string $type, string $declared, Closure $fills): string
    {
        if (TsTypeString::isUnknownOnly($type)) {
            return ! TsTypeString::isUnknownOnly($declared) && $fills($declared) ? TsTypeString::withNullArmOf($type, $declared) : $type;
        }

        $declaredArms = array_values(array_diff(TsTypeString::splitTopLevelUnion($declared), ['null']));
        $arms = TsTypeString::splitTopLevelUnion($type);

        if (count($arms) > 1) {
            $filled = array_map(static function (string $arm) use ($declaredArms, $fills): string {
                $match = self::armOfKind($arm, $declaredArms);

                return $match === null ? $arm : self::filled($arm, $match, $fills);
            }, $arms);

            return $filled === $arms ? $type : implode(' | ', $filled);
        }

        $match = self::armOfKind($type, $declaredArms);

        return match (true) {
            $match === null || ! TsTypeString::typeNameOccursIn('unknown', $type) => $type,
            TsTypeShape::members($type) !== null => self::membersFilled($type, $match, $fills),
            TsTypeShape::recordArguments($type) !== null => self::recordFilled($type, $match, $fills),
            default => self::elementFilled($type, $match, $fills),
        };
    }

    /**
     * An object type with each member's `unknown` positions filled from $declared's member of that key, `?` aside.
     *
     * @param  Closure(string): bool  $fills
     */
    private static function membersFilled(string $object, string $declared, Closure $fills): string
    {
        $declaredMembers = [];

        foreach (TsTypeShape::members($declared) ?? [] as [$key, $value]) {
            $declaredMembers[rtrim($key, '?')] = $value;
        }

        $members = [];
        $changed = false;

        foreach (TsTypeShape::members($object) ?? [] as [$key, $value]) {
            $fill = $declaredMembers[rtrim($key, '?')] ?? null;
            $filled = $fill === null ? $value : self::filled($value, $fill, $fills);
            $changed = $changed || $filled !== $value;
            $members[] = $key.': '.$filled;
        }

        return $changed ? '{ '.implode('; ', $members).' }' : $object;
    }

    /**
     * A `Record<K, V>` with V's `unknown` positions filled from $declared's value.
     *
     * @param  Closure(string): bool  $fills
     */
    private static function recordFilled(string $record, string $declared, Closure $fills): string
    {
        [$key, $value] = TsTypeShape::recordArguments($record) ?? ['', ''];
        $filled = self::filled($value, TsTypeShape::recordArguments($declared)[1] ?? 'unknown', $fills);

        return $filled === $value ? $record : 'Record<'.$key.', '.$filled.'>';
    }

    /**
     * A `T[]` list with T's `unknown` positions filled from $declared's element.
     *
     * @param  Closure(string): bool  $fills
     */
    private static function elementFilled(string $list, string $declared, Closure $fills): string
    {
        $element = (string) TsTypeShape::elementType($list);
        $filled = self::filled($element, (string) TsTypeShape::elementType($declared), $fills);

        return $filled === $element ? $list : TsTypeShape::postfixOperand($filled).'[]';
    }

    /**
     * The one arm of $arms of $arm's kind; null where none or several are, or $arm is of no kind kindOf() reads.
     *
     * @param  list<string>  $arms
     */
    private static function armOfKind(string $arm, array $arms): ?string
    {
        $kind = self::kindOf($arm);
        $matches = $kind === null ? [] : array_values(array_filter($arms, static fn (string $other): bool => self::kindOf($other) === $kind));

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Whether one arm is an object literal, a Record or a `T[]` list; null for any other arm.
     *
     * @return 'object'|'record'|'list'|null
     */
    private static function kindOf(string $arm): ?string
    {
        return match (true) {
            TsTypeShape::members($arm) !== null => 'object',
            TsTypeShape::recordArguments($arm) !== null => 'record',
            $arm !== '[]' && str_ends_with($arm, '[]') && TsTypeShape::elementType($arm) !== null => 'list',
            default => null,
        };
    }
}
