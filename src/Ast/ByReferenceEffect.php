<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * What a call leaves in a variable it passes to a by-reference parameter: the value it held, while that value has
 * the form the call keeps, or a value the scan does not follow.
 *
 * @internal
 */
enum ByReferenceEffect
{
    /** ksort() and uksort() move keys in order, which keeps any array. */
    case KeepsAny;

    /** sort(), rsort(), usort() and shuffle() re-index, which keeps a list but lists an object's values. */
    case KeepsList;

    /** asort(), krsort() and the other sorts that keep each key keep an object, but turn a list into one. */
    case KeepsObject;

    /** A count out-parameter writes an int. */
    case KeepsNumber;

    /** array_push() and array_unshift() add values to a list. */
    case Appends;

    /** array_walk() and a by-reference `foreach` write each element in place. */
    case WritesElements;

    /** array_walk_recursive() writes each value below the arrays it descends, at any depth. */
    case WritesLeaves;

    /** Anything else: the callee writes a value the scan does not follow. */
    case Replaces;

    /**
     * Whether a value of a type keeps it past the call, given the types of the values the call adds or writes, and the
     * key path to the member the call takes, which must keep its own form.
     *
     * A `null` arm is left out of the form test: the call takes only an array, so a null never gets past it.
     *
     * @param  list<string>  $values
     * @param  list<string>  $path
     */
    public function keeps(string $type, array $values = [], array $path = []): bool
    {
        foreach ($path as $key) {
            // Whatever the call leaves in a member typed `unknown`, the type published for it still holds.
            if ($type === 'unknown') {
                return $this !== self::Replaces;
            }

            $member = TsTypeShape::memberType($type, $key)
                ?? (is_numeric($key) ? self::listElements($type)[0] ?? null : null);

            if ($member === null) {
                return false;
            }

            $type = $member;
        }

        if ($path !== [] && $type === 'unknown') {
            return $this !== self::Replaces;
        }

        $arms = array_values(array_filter(
            TsTypeShape::splitTopLevel($type, ['|']),
            static fn (string $arm): bool => $arm !== 'null',
        ));

        if ($arms === [] && $this !== self::KeepsAny) {
            return false;
        }

        return match ($this) {
            self::KeepsAny => true,
            self::KeepsList => array_all($arms, static fn (string $arm): bool => self::listElements($arm) !== null),
            self::KeepsObject => array_all($arms, TsTypeShape::isObjectLike(...)),
            self::KeepsNumber => $type === 'number',
            self::Appends => array_all($arms, static fn (string $arm): bool => self::holdsEach(self::listElements($arm), $values)),
            self::WritesElements => array_all($arms, static fn (string $arm): bool => self::holdsEach(self::elementTypes($arm), $values)),
            self::WritesLeaves => array_all($arms, static fn (string $arm): bool => self::holdsEach(self::leafTypes($arm), $values)),
            self::Replaces => false,
        };
    }

    /**
     * The one element type of a list type such as `T[]`, or null for a tuple or any other type.
     *
     * @return list<string>|null
     */
    private static function listElements(string $arm): ?array
    {
        $element = TsTypeShape::isWrapped($arm, '[', ']') ? null : TsTypeShape::elementType($arm);

        return $element === null ? null : [$element];
    }

    /**
     * The type of each element a list, a tuple, an object or a Record holds, or null when the arm holds none the scan
     * can name.
     *
     * @return list<string>|null
     */
    private static function elementTypes(string $arm): ?array
    {
        $list = self::listElements($arm);

        if ($list !== null) {
            return $list;
        }

        if (TsTypeShape::isWrapped($arm, '[', ']')) {
            return array_map(
                static fn (string $member): string => rtrim($member, '?'),
                TsTypeShape::splitTopLevel(substr($arm, 1, -1), [',']),
            );
        }

        if (str_starts_with($arm, 'Record<') && str_ends_with($arm, '>')) {
            $value = TsTypeShape::splitTopLevel(substr($arm, 7, -1), [','])[1] ?? null;

            return $value === null ? null : [$value];
        }

        $members = TsTypeShape::members($arm);

        return $members === null ? null : array_map(static fn (array $member): string => $member[1], $members);
    }

    /**
     * The types of the values a recursive walk visits below an array arm: at each element, the union of its arms that
     * hold no array, and below each that does, the same again. Null when the arm holds no array.
     *
     * @return list<string>|null
     */
    private static function leafTypes(string $arm): ?array
    {
        $elements = self::elementTypes($arm);

        if ($elements === null) {
            return null;
        }

        $leaves = [];

        foreach ($elements as $element) {
            $values = [];

            foreach (TsTypeShape::splitTopLevel($element, ['|']) as $part) {
                $below = self::leafTypes($part);

                if ($below === null) {
                    $values[] = $part;
                } else {
                    array_push($leaves, ...$below);
                }
            }

            if ($values !== []) {
                $leaves[] = implode(' | ', $values);
            }
        }

        return $leaves;
    }

    /**
     * Whether every value's type falls within every element type, so writing any value into any element keeps it.
     *
     * @param  list<string>|null  $elements
     * @param  list<string>  $values
     */
    private static function holdsEach(?array $elements, array $values): bool
    {
        return $elements !== null && array_all(
            $elements,
            static fn (string $element): bool => array_all(
                $values,
                static fn (string $value): bool => self::within($value, $element),
            ),
        );
    }

    /**
     * Whether every arm of a type is an arm of another, or the other is `unknown`, which holds anything.
     */
    private static function within(string $type, string $of): bool
    {
        $held = TsTypeShape::splitTopLevel($of, ['|']);

        return in_array('unknown', $held, true)
            || array_all(TsTypeShape::splitTopLevel($type, ['|']), static fn (string $arm): bool => in_array($arm, $held, true));
    }
}
