<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;

/**
 * What a call leaves in a variable it passes to a by-reference parameter: the value it held, while that value has
 * the form the call keeps, or a value the scan does not follow.
 *
 * @phpstan-type Written Closure(string, string): list<string>
 * @phpstan-type KeyedElement array{string, string}
 *
 * @internal
 */
enum ByReferenceEffect
{
    /** ksort() moves keys into ascending order, which keeps any array. */
    case KeepsAny;

    /** sort(), rsort(), usort(), shuffle(), array_pop() and array_shift() keep a list; an object loses its keys. */
    case KeepsList;

    /** asort(), uksort() and the other sorts that keep each key keep an object, and key a list out of order. */
    case KeepsObject;

    /** A count out-parameter writes an int. */
    case KeepsNumber;

    /** array_push(), array_unshift() and array_splice() add values to a list. */
    case Appends;

    /** array_walk() and a by-reference `foreach` write each element in place. */
    case WritesElements;

    /** array_walk_recursive() writes each value below the arrays it descends, at any depth. */
    case WritesLeaves;

    /** preg_match() with no flag and no named group writes the groups it captures: a list of strings. */
    case WritesMatches;

    /** Anything else: the callee writes a value the scan does not follow. */
    case Replaces;

    /** What preg_match()'s matches replace: an empty or string list, which a list of strings stands for either way. */
    private const array MATCHES_REPLACE = ['never[]', '[]', 'string[]'];

    /**
     * The type a value of a type holds once the call returns, or null when the scan cannot tell: the type itself while
     * the call keeps its form, and a list a key-keeping sort reorders as `Record<number, T>`, json_encode()'s object,
     * unless the response re-indexes it into the list it was. A key path names the member the call takes.
     *
     * A `null` arm stays as it is: the call takes only an array, so a null never gets past it.
     *
     * @param  Written  $written  the types the call adds, or writes into an element of the first type under a key of
     *                            the second
     * @param  list<string>  $path
     */
    public function left(string $type, Closure $written, array $path = [], bool $reindexed = false): ?string
    {
        if ($path === []) {
            return $this->leftWhole($type, $written, $reindexed);
        }

        $key = array_shift($path);
        $member = TsTypeShape::memberType($type, $key)
            ?? (is_numeric($key) ? self::listElements($type)[0] ?? null : null);

        // Whatever the call leaves in a member typed `unknown`, the type published for it still holds.
        if ($member === null || $member === 'unknown') {
            return $member === null || $this === self::Replaces ? null : $type;
        }

        $left = $this->left($member, $written, $path, $reindexed);

        return match (true) {
            $left === null => null,
            $left === $member => $type,
            default => self::withMember($type, $key, $left),
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
     * A list's one element under an int key, or null for any other type.
     *
     * @return list<KeyedElement>|null
     */
    private static function listedElements(string $arm): ?array
    {
        $element = self::listElements($arm)[0] ?? null;

        return $element === null ? null : [[$element, 'number']];
    }

    /**
     * What a list or a tuple becomes once its keys are out of order: an object of its elements under int keys. An
     * empty tuple has no key to move. Null for any other type.
     */
    private static function keyedOutOfOrder(string $arm): ?string
    {
        if ($arm === '[]') {
            return $arm;
        }

        $elements = self::listedElements($arm) ?? (TsTypeShape::isWrapped($arm, '[', ']') ? self::elements($arm) : null);

        return $elements === null
            ? null
            : 'Record<number, '.implode(' | ', array_values(array_unique(array_column($elements, 0)))).'>';
    }

    /**
     * Each element a list, a tuple, an object or a Record holds, with the type of the key it sits under: an int for a
     * list, a tuple or a numeric key, else a string. Null when the arm holds none the scan can name.
     *
     * @return list<KeyedElement>|null
     */
    private static function elements(string $arm): ?array
    {
        $listed = self::listedElements($arm);

        if ($listed !== null) {
            return $listed;
        }

        if (TsTypeShape::isWrapped($arm, '[', ']')) {
            return array_map(
                static fn (string $member): array => [rtrim($member, '?'), 'number'],
                TsTypeShape::splitTopLevel(substr($arm, 1, -1), [',']),
            );
        }

        if (str_starts_with($arm, 'Record<') && str_ends_with($arm, '>')) {
            $arguments = TsTypeShape::splitTopLevel(substr($arm, 7, -1), [',']);

            return count($arguments) === 2
                ? [[$arguments[1], match ($arguments[0]) {
                    'number' => 'number',
                    'string' => 'string',
                    default => 'string | number',
                }]]
                : null;
        }

        $members = TsTypeShape::members($arm);

        return $members === null ? null : array_map(
            static fn (array $member): array => [$member[1], TsTypeShape::isNumericKey($member[0]) ? 'number' : 'string'],
            $members,
        );
    }

    /**
     * The values a recursive walk visits below an array arm, with their keys: at each element, the union of its arms
     * that hold no array, and below each that does, the same again. Null when the arm holds no array.
     *
     * @return list<KeyedElement>|null
     */
    private static function leaves(string $arm): ?array
    {
        $elements = self::elements($arm);

        if ($elements === null) {
            return null;
        }

        $leaves = [];

        foreach ($elements as [$element, $key]) {
            $values = [];

            foreach (TsTypeShape::splitTopLevel($element, ['|']) as $part) {
                $below = self::leaves($part);

                if ($below === null) {
                    $values[] = $part;
                } else {
                    array_push($leaves, ...$below);
                }
            }

            if ($values !== []) {
                $leaves[] = [implode(' | ', $values), $key];
            }
        }

        return $leaves;
    }

    /**
     * A type with the member under a key replaced in each object literal arm, and joined to the value of each Record
     * arm, whose other keys keep theirs; null when no such arm holds the key.
     */
    private static function withMember(string $type, string $key, string $member): ?string
    {
        $found = false;
        $arms = [];

        foreach (TsTypeShape::splitTopLevel($type, ['|']) as $arm) {
            $arguments = str_starts_with($arm, 'Record<') && str_ends_with($arm, '>')
                ? TsTypeShape::splitTopLevel(substr($arm, 7, -1), [','])
                : [];

            if (count($arguments) === 2) {
                $found = true;
                $arms[] = 'Record<'.$arguments[0].', '.implode(' | ', array_values(array_unique([
                    ...TsTypeShape::splitTopLevel($arguments[1], ['|']),
                    $member,
                ]))).'>';

                continue;
            }

            $members = TsTypeShape::members($arm);

            if ($members === null) {
                $arms[] = $arm;

                continue;
            }

            $printed = [];

            foreach ($members as [$name, $value]) {
                if (trim(rtrim($name, '?'), '\'"') === $key) {
                    $found = true;
                    $value = $member;
                }

                $printed[] = $name.': '.$value;
            }

            $arms[] = '{ '.implode('; ', $printed).' }';
        }

        return $found ? implode(' | ', $arms) : null;
    }

    /**
     * Whether every value the call writes into each element falls within that element's type, so writing any of them
     * keeps it.
     *
     * @param  list<KeyedElement>|null  $elements
     * @param  Written  $written
     */
    private static function holdsEach(?array $elements, Closure $written): bool
    {
        return $elements !== null && array_all(
            $elements,
            static fn (array $element): bool => array_all(
                $written($element[0], $element[1]),
                static fn (string $value): bool => self::within($value, $element[0]),
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

    /**
     * What the call leaves in the whole value.
     *
     * @param  Written  $written
     */
    private function leftWhole(string $type, Closure $written, bool $reindexed): ?string
    {
        if ($this === self::KeepsNumber) {
            return $type === 'number' ? $type : null;
        }

        $arms = TsTypeShape::splitTopLevel($type, ['|']);

        // A call that may not run leaves the value held, so the matches replace only one they can stand for.
        if ($this === self::WritesMatches) {
            return array_diff($arms, self::MATCHES_REPLACE) === [] ? 'string[]' : null;
        }

        if (! array_any($arms, static fn (string $arm): bool => $arm !== 'null')) {
            return $this === self::KeepsAny ? $type : null;
        }

        $left = [];

        foreach ($arms as $arm) {
            $kept = $arm === 'null' ? $arm : $this->leftArm($arm, $written, $reindexed);

            if ($kept === null) {
                return null;
            }

            $left[] = $kept;
        }

        return $left === $arms ? $type : implode(' | ', array_values(array_unique($left)));
    }

    /**
     * What the call leaves in one arm that is not `null`.
     *
     * @param  Written  $written
     */
    private function leftArm(string $arm, Closure $written, bool $reindexed): ?string
    {
        return match ($this) {
            self::KeepsAny => $arm,
            self::KeepsList => self::listElements($arm) === null ? null : $arm,
            self::KeepsObject => match (true) {
                TsTypeShape::isObjectLike($arm) => $arm,
                $reindexed && self::listElements($arm) !== null => $arm,
                // A re-indexed tuple lists its members in sorted order, never in the declared one.
                $reindexed && $arm !== '[]' && TsTypeShape::isWrapped($arm, '[', ']') => TsTypeShape::listType(
                    array_column(self::elements($arm) ?? [], 0),
                ),
                default => self::keyedOutOfOrder($arm),
            },
            self::Appends => self::holdsEach(self::listedElements($arm), $written) ? $arm : null,
            self::WritesElements => self::holdsEach(self::elements($arm), $written) ? $arm : null,
            self::WritesLeaves => self::holdsEach(self::leaves($arm), $written) ? $arm : null,
            self::KeepsNumber, self::WritesMatches, self::Replaces => null,
        };
    }
}
