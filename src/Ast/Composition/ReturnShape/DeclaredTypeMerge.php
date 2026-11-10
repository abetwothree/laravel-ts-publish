<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape;

use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\PrintedKey;

/**
 * Merges the body's reading of a position with the type a method's `@return` docblock declares there, giving every
 * position an answer: a reading left `unknown` takes the declaration, a shape keeps the members the body sends, a list
 * or record merges its element or value, and a declaration within the reading refines it arm by arm, else it stays.
 *
 * Each side is a type beside the class behind each token of the names in play, in print order, so classes compare by
 * identity, never by spelling, and an enum the body wraps in an `EnumResource` is never that enum.
 *
 * @phpstan-import-type Slots from TokenSlots
 *
 * @phpstan-type Typed = array{string, Slots}
 * @phpstan-type Verdict = 'body'|'declared'|'unknown'
 *
 * @internal
 */
final readonly class DeclaredTypeMerge
{
    /** The arms that tell nothing of a value but its container: read as `unknown`, as the top of a key reads them. */
    private const array VAGUE = ['unknown', 'unknown[]', 'Record<string, unknown>', 'Record<number, unknown>', 'Record<string | number, unknown>'];

    /** The declared container kind a body list or record of the other kind pairs with, merging its contents with it. */
    private const array PAIRS_ACROSS = ['list' => 'record', 'record' => 'list'];

    /**
     * @param  list<string>  $names  the names whose every token a side's slots give a class, or null
     * @param  list<class-string>  $bodyWraps  the enums the body wraps in an `EnumResource` where it spells them bare
     * @param  list<string>  $openReadings  each reading the body leaves open, as ValueResult `openReadings` spells it:
     *                                      its path from the position merged, a NUL, and its type
     */
    public function __construct(
        private array $names,
        private array $bodyWraps = [],
        private array $openReadings = [],
    ) {}

    /**
     * Whether one class-less arm is a subtype of another: the same type, or a literal of the other's scalar. `null` is
     * within only `null`, and only `unknown` is within `unknown`, which merge() answers arm by arm instead.
     */
    public static function armWithin(string $arm, string $of): bool
    {
        return $arm === $of || match ($of) {
            'string' => strlen($arm) >= 2 && in_array($arm[0], ['"', "'", '`'], true) && $arm[-1] === $arm[0],
            'number' => is_numeric($arm),
            'boolean' => in_array($arm, ['true', 'false'], true),
            default => false,
        };
    }

    /**
     * The type to publish at a position: the body's reading merged with the declared type there.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @param  string  $at  the position's path, as $openReadings spells it
     * @return Typed
     */
    public function merge(array $body, array $declared, string $at = ''): array
    {
        // A union that left out an arm no rule types beside its `null` may hold whatever that arm held, which the
        // declaration then types; an `unknown` it leaves unfilled is not published.
        if (self::tells($declared[0]) > 0 && TsTypeString::hasNullArm($body[0]) && in_array($at."\0".$body[0], $this->openReadings, true)) {
            $merged = $this->mergeReading([$body[0].' | unknown', $body[1]], $declared, $at);
            $arms = array_values(array_diff(TsTypeString::splitTopLevelUnion($merged[0]), ['unknown']));

            return $arms === [] ? $body : [implode(' | ', $arms), $merged[1]];
        }

        return $this->mergeReading($body, $declared, $at);
    }

    /**
     * How much a type tells of its value, its `null` aside: 0 for `unknown` or `mixed`, 1 for no more than its
     * container, as `unknown[]` or a `Record` of `unknown` does, and 2 for any other type.
     *
     * @return 0|1|2
     */
    private static function tells(string $type): int
    {
        $arms = array_values(array_diff(TsTypeString::splitTopLevelUnion($type), ['null']));

        return match (true) {
            $type === 'mixed' || $arms === ['unknown'] => 0,
            $arms !== [] && array_all($arms, static fn (string $arm): bool => in_array($arm, self::VAGUE, true)) => 1,
            default => 2,
        };
    }

    /**
     * Whether one arm is an object literal, a Record or a `T[]` list, which merge member by member, by value or by
     * element; null for every other arm, which compares whole.
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

    /**
     * The arms of a union that compare whole, as no container merges them.
     *
     * @param  list<Typed>  $arms
     * @return list<Typed>
     */
    private static function atoms(array $arms): array
    {
        return array_values(array_filter($arms, static fn (array $arm): bool => self::kindOf($arm[0]) === null));
    }

    /**
     * The key a printed member key names, so `0`, `"0"` and `'0'` are one key; an index signature as printed.
     */
    private static function keyName(string $key): string
    {
        return PrintedKey::decode($key) ?? rtrim($key, '?');
    }

    /**
     * How well two arms' members match: the literals both spell alike under one key, then the keys both hold, read
     * through a list's element or a record's value; nothing for an arm holding no object.
     *
     * @return array{int, int}
     */
    private static function matchOf(string $body, string $declared): array
    {
        $inner = static fn (string $arm): string => TsTypeShape::recordArguments($arm)[1] ?? (self::kindOf($arm) === 'list' ? (string) TsTypeShape::elementType($arm) : $arm);
        $members = static function (string $object): array {
            $byKey = [];

            foreach (TsTypeShape::members($object) ?? [] as [$key, $value]) {
                $byKey[rtrim($key, '?')] = $value;
            }

            return $byKey;
        };
        $bodyMembers = $members($inner($body));
        $declaredMembers = $members($inner($declared));
        $shared = array_intersect_key($bodyMembers, $declaredMembers);
        $literal = static fn (string $value): bool => TsTypeShape::isScalar($value) && ! in_array($value, ['string', 'number', 'boolean'], true);
        $alike = array_filter($shared, static fn (string $value, string $key): bool => $literal($value) && $value === $declaredMembers[$key], ARRAY_FILTER_USE_BOTH);

        return [count($alike), count($shared)];
    }

    /**
     * merge() of a reading no union left an untyped arm out of.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @return Typed
     */
    private function mergeReading(array $body, array $declared, string $at): array
    {
        $told = [self::tells($body[0]), self::tells($declared[0])];

        // A body reading only `null` tells less than a container.
        if ($body[0] === 'null' && $told[1] === 1) {
            return [TsTypeString::withNullArm($declared[0]), $declared[1]];
        }

        // Where either side tells no more than its container, the side telling more is taken whole.
        if (min($told) < 2) {
            // `withNullArm()` appends a `null`, which spells no class, so the declared slots still line up.
            return $told[1] > $told[0] ? [TsTypeString::withNullArmOf($body[0], $declared[0]), $declared[1]] : $body;
        }

        $printedArms = $this->arms($body);
        $opens = array_map(fn (array $arm): bool => $this->isOpen($at, $arm[0]), $printedArms);
        // An intersection of objects, as a spread beside keys prints, merges as the one object it sends.
        $bodyArms = array_map(fn (array $arm): array => $this->flattened($arm) ?? $arm, $printedArms);
        $declaredArms = $this->arms($declared);
        $pairs = $this->pairs($bodyArms, $declaredArms);
        $verdict = $this->verdict($bodyArms, $declaredArms, $pairs);

        $merged = [];
        $declaredAtoms = self::atoms($declaredArms);

        foreach ($bodyArms as $index => $arm) {
            if (self::kindOf($arm[0]) !== null) {
                // A body arm several declared arms match alike merges with each, every result an arm of its own.
                array_push($merged, ...array_merge([], ...array_map(
                    fn (int $paired): array => $this->containerMerged($arm, $declaredArms[$paired], $at, $opens[$index]),
                    $pairs[$index] ?? [],
                )), ...(isset($pairs[$index]) ? [] : [$printedArms[$index]]));
            } elseif ($verdict === 'declared') {
                // A body arm the declaration refines none of stays, so it never stops admitting what the body sends.
                $refined = array_values(array_filter($declaredAtoms, fn (array $declared): bool => $this->within([$declared], [$arm], false)));
                array_push($merged, ...($refined === [] ? [$arm] : $refined));
            } else {
                array_push($merged, ...($verdict === 'unknown' && $arm[0] === 'unknown' ? $this->uncovered($bodyArms, $declaredArms, $pairs) : [$arm]));
            }
        }

        // The arm a declaration adds, its `null` beside a body that reads none, as verdict() only takes such a one.
        if ($verdict === 'declared') {
            array_push($merged, ...array_filter($declaredAtoms, fn (array $declared): bool => ! $this->within([$declared], self::atoms($bodyArms), false)));
        }

        return $this->joined($merged, $body);
    }

    /**
     * Which side decides the arms no container pairs: the declared ones, arm by arm, where they are within the body's,
     * with or without a `null` beside them; else the body's, an `unknown` arm of which takes the declared arms no other
     * body arm covers.
     *
     * @param  list<Typed>  $bodyArms
     * @param  list<Typed>  $declaredArms
     * @param  array<int, list<int>>  $pairs
     * @return Verdict
     */
    private function verdict(array $bodyArms, array $declaredArms, array $pairs): string
    {
        $bodyAtoms = self::atoms($bodyArms);
        $declaredAtoms = self::atoms($declaredArms);
        $leftUnknown = in_array('unknown', array_column($bodyAtoms, 0), true);
        $declaredBesideNull = array_values(array_filter($declaredAtoms, static fn (array $arm): bool => $arm[0] !== 'null'));

        return match (true) {
            $bodyAtoms === [] || $declaredAtoms === [] => $leftUnknown && $this->uncovered($bodyArms, $declaredArms, $pairs) !== [] ? 'unknown' : 'body',
            $this->within($declaredAtoms, $bodyAtoms, false) => 'declared',
            $this->within($bodyAtoms, $declaredAtoms, true) => 'body',
            ! $leftUnknown && $declaredBesideNull !== [] && $this->within($declaredBesideNull, $bodyAtoms, false) => 'declared',
            $leftUnknown => 'unknown',
            default => 'body',
        };
    }

    /**
     * Whether every arm of one side is within some arm of the other.
     *
     * @param  list<Typed>  $arms
     * @param  list<Typed>  $of
     * @param  bool  $armsAreBody  whether $arms is the body's side and $of the declared one
     */
    private function within(array $arms, array $of, bool $armsAreBody): bool
    {
        return array_all($arms, fn (array $arm): bool => array_any(
            $of,
            fn (array $other): bool => self::armWithin($this->identity($arm, $armsAreBody), $this->identity($other, ! $armsAreBody)),
        ));
    }

    /**
     * The declared arms no body arm but an `unknown` one covers: a container no body arm pairs, or an arm within no
     * other body arm.
     *
     * @param  list<Typed>  $bodyArms
     * @param  list<Typed>  $declaredArms
     * @param  array<int, list<int>>  $pairs
     * @return list<Typed>
     */
    private function uncovered(array $bodyArms, array $declaredArms, array $pairs): array
    {
        $typed = array_values(array_filter($bodyArms, static fn (array $arm): bool => $arm[0] !== 'unknown' && self::kindOf($arm[0]) === null));

        return array_values(array_filter(
            $declaredArms,
            fn (array $arm, int $index): bool => self::kindOf($arm[0]) === null
                ? ! $this->within([$arm], $typed, false)
                : ! in_array($index, array_merge([], ...array_values($pairs)), true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Each body container arm's index beside the indexes of the declared arms it merges with: the one of its kind, or
     * of several those whose members match it best; else, for a shape, acrossFromShape(), and for a list or record,
     * those of the kind PAIRS_ACROSS names, matched alike.
     *
     * @param  list<Typed>  $bodyArms
     * @param  list<Typed>  $declaredArms
     * @return array<int, list<int>>
     */
    private function pairs(array $bodyArms, array $declaredArms): array
    {
        $pairs = [];

        foreach ($bodyArms as $index => $arm) {
            $kind = self::kindOf($arm[0]);

            if ($kind === null) {
                continue;
            }

            $ofKind = static fn (string $wanted): array => array_keys(array_filter($declaredArms, static fn (array $declared): bool => self::kindOf($declared[0]) === $wanted));
            $same = $ofKind($kind);
            $across = $kind === 'object' ? [] : $ofKind(self::PAIRS_ACROSS[$kind]);
            $match = match (true) {
                $same !== [] => $this->bestMatches($arm, $declaredArms, $same),
                $kind === 'object' => $this->acrossFromShape($arm, $declaredArms, $ofKind('list'), $ofKind('record')),
                $across !== [] => $this->bestMatches($arm, $declaredArms, $across),
                default => [],
            };

            if ($match !== []) {
                $pairs[$index] = $match;
            }
        }

        return $pairs;
    }

    /**
     * The declared lists or records a body shape with no declared shape beside it merges with, the best fit first: a
     * list whose element holds a shape sharing a key with it, else a record, else any list.
     *
     * @param  Typed  $arm
     * @param  list<Typed>  $declaredArms
     * @param  list<int>  $lists
     * @param  list<int>  $records
     * @return list<int>
     */
    private function acrossFromShape(array $arm, array $declaredArms, array $lists, array $records): array
    {
        $shapeScore = fn (int $list): array => max([[0, 0], ...array_map(
            static fn (string $part): array => self::kindOf($part) === 'object' ? self::matchOf($arm[0], $part) : [0, 0],
            TsTypeString::splitTopLevelUnion((string) TsTypeShape::elementType($declaredArms[$list][0])),
        )]);
        $scores = array_map($shapeScore, $lists);
        $best = $scores === [] ? [0, 0] : max($scores);

        return match (true) {
            $best[1] > 0 => array_map(static fn (int $at): int => $lists[$at], array_keys($scores, $best, true)),
            $records !== [] => $records,
            default => $lists,
        };
    }

    /**
     * The declared arms among $candidates whose members best match the body arm's: the most discriminant literals both
     * spell alike, then the most keys both hold; every one of them where several match it alike.
     *
     * @param  Typed  $arm
     * @param  list<Typed>  $declaredArms
     * @param  non-empty-list<int>  $candidates
     * @return list<int>
     */
    private function bestMatches(array $arm, array $declaredArms, array $candidates): array
    {
        $scores = array_map(fn (int $index): array => self::matchOf($arm[0], $declaredArms[$index][0]), $candidates);
        $best = max($scores);

        return array_map(static fn (int $at): int => $candidates[$at], array_keys($scores, $best, true));
    }

    /**
     * A body container arm merged with the declared arm it pairs with: an object member by member with a declared
     * object or each best matched shape of a declared list's element, else each member with a declared record's value
     * or list's element; a list by element and a record by value, either with a declared list's or record's.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @param  bool  $open  whether the body arm is a shape the engine cannot name every key of
     * @return list<Typed>
     */
    private function containerMerged(array $body, array $declared, string $at, bool $open): array
    {
        $kind = self::kindOf($declared[0]);

        if (self::kindOf($body[0]) !== 'object') {
            return [self::kindOf($body[0]) === 'record'
                ? $this->valueMerged($body, $this->contents($declared), $at)
                : $this->elementMerged($body, $this->contents($declared), $at)];
        }

        if ($kind === 'object') {
            return [$this->membersMerged($body, $declared, true, $at, $open)];
        }

        // A shape under a declared list merges with the shapes its element holds, the best matched, else each member
        // with the whole element, as with a record's value.
        $contents = $this->contents($declared);
        $parts = $kind === 'list' ? $this->arms($contents) : [];
        $shapes = array_keys(array_filter($parts, static fn (array $part): bool => self::kindOf($part[0]) === 'object'));

        return $shapes === []
            ? [$this->membersMerged($body, $contents, false, $at, $open)]
            : array_map(fn (int $shape): array => $this->membersMerged($body, $parts[$shape], true, $at, $open), $this->bestMatches($body, $parts, $shapes));
    }

    /**
     * Whether the body reads the type at a path as a shape the engine cannot name every key of, an intersection
     * wrapped or not.
     */
    private function isOpen(string $at, string $type): bool
    {
        $inner = TsTypeShape::isWrapped($type, '(', ')') ? trim(substr($type, 1, -1)) : $type;

        return in_array($at."\0".$type, $this->openReadings, true) || in_array($at."\0".$inner, $this->openReadings, true);
    }

    /**
     * A body arm that is an intersection of objects as the one object it sends, a later part's key replacing an
     * earlier one's as PHP's spread does; null for any other arm.
     *
     * @param  Typed  $arm
     * @return Typed|null
     */
    private function flattened(array $arm): ?array
    {
        $parts = TsTypeShape::intersectedObjects($arm[0]);

        if ($parts === null) {
            return null;
        }

        /** @var array<array-key, array{string, Typed}> $members each member's key and value, by its name */
        $members = [];
        $from = 0;

        foreach ($parts as [$object, $dropped, $part]) {
            // An `Omit<>`'s object leads it, so its tokens lead the part's.
            foreach ($this->memberSlots([$object, $arm[1]], $from) as [$key, $value, $keySlots, $valueSlots]) {
                $name = self::keyName($key);

                if (! in_array($name, $dropped, true)) {
                    unset($members[$name]);
                    $members[$name] = [$key, [$value, [...$keySlots, ...$valueSlots]]];
                }
            }

            $from += $this->count($part);
        }

        return [
            '{ '.implode('; ', array_map(static fn (array $member): string => $member[0].': '.$member[1][0], $members)).' }',
            array_merge([], ...array_map(static fn (array $member): array => $member[1][1], array_values($members))),
        ];
    }

    /**
     * Each member of an object type beside its key's and its value's share of the slots, which start at $from.
     *
     * @param  Typed  $object
     * @return list<array{string, string, Slots, Slots}> each member's printed key, value, key slots and value slots
     */
    private function memberSlots(array $object, int $from = 0): array
    {
        $members = [];

        foreach (TsTypeShape::members($object[0]) ?? [] as [$key, $value]) {
            $keySlots = array_slice($object[1], $from, $this->count($key));
            $from += $this->count($key);
            $members[] = [$key, $value, $keySlots, array_slice($object[1], $from, $this->count($value))];
            $from += $this->count($value);
        }

        return $members;
    }

    /**
     * A declared list's element or record's value, beside its share of the slots.
     *
     * @param  Typed  $declared
     * @return Typed
     */
    private function contents(array $declared): array
    {
        $record = TsTypeShape::recordArguments($declared[0]);

        return $record === null
            ? [(string) TsTypeShape::elementType($declared[0]), $declared[1]]
            : [$record[1], array_slice($declared[1], $this->count($record[0]))];
    }

    /**
     * An object type with each member the body sends merged, $byKey, with the declared object's member of the key it
     * names, else with the declared value, keeping the body's keys, order and optional flags. A member only the
     * declaration has follows where it is optional or the shape is $open, and goes where the body never sends it.
     *
     * One a body index signature's pattern covers merges with that signature's value, which widens to admit it.
     *
     * @param  Typed  $body
     * @param  Typed  $declared  the declared object, or the value every member merges with
     * @return Typed
     */
    private function membersMerged(array $body, array $declared, bool $byKey, string $at, bool $open): array
    {
        $declaredMembers = [];

        foreach ($byKey ? $this->memberSlots($declared) : [] as [$key, $value, $keySlots, $valueSlots]) {
            $declaredMembers[self::keyName($key)] = [$key, [$value, $valueSlots], $keySlots];
        }

        $value = $byKey ? null : $declared;

        /** @var list<array{string, Typed, Slots}> $members each member's printed key, value and key slots */
        $members = [];

        foreach ($this->memberSlots($body) as [$key, $text, $keySlots, $valueSlots]) {
            $read = [$text, $valueSlots];
            $name = self::keyName($key);
            $paired = $declaredMembers[$name] ?? null;
            $fill = $value ?? $paired[1] ?? null;
            unset($declaredMembers[$name]);
            // A numeric key prints as the declaration spells it, `0` where the body quotes `"0"`.
            $printed = $paired !== null && is_numeric($name) ? rtrim($paired[0], '?').(str_ends_with($key, '?') ? '?' : '') : $key;
            $members[] = [$printed, $fill === null ? $read : $this->merge($read, $fill, $at.'.'.rtrim($key, '?')), $keySlots];
        }

        /** @var array<int, Typed> $signatures each index signature member's value, by its position */
        $signatures = [];

        foreach ($members as $position => [$key, $read]) {
            if (IndexSignatureKey::is($key)) {
                $signatures[$position] = $read;
            }
        }

        foreach ($declaredMembers as $name => [$key, $text, $keySlots]) {
            $covering = array_filter(
                array_keys($signatures),
                static fn (int $position): bool => IndexSignatureKey::covers($members[$position][0], (string) $name),
            );

            if ($covering !== []) {
                // A key several signatures cover may be sent under any, so it reads as the union of their values.
                $values = array_map(static fn (int $position): array => $signatures[$position], array_values($covering));
                $read = [implode(' | ', array_column($values, 0)), array_merge([], ...array_column($values, 1))];
                $merged = $this->merge($this->joined($this->arms($read), $read), $text, $at.'.'.$name);
                $members[] = [$key, $merged, $keySlots];

                foreach ($covering as $position) {
                    $signatures[$position] = $this->admitting($signatures[$position], $merged);
                }
            } elseif (str_ends_with($key, '?') || $open) {
                $members[] = [$key, $text, $keySlots];
            }
        }

        $printed = array_map(
            static fn (array $member, int $position): array => [$member[0], $signatures[$position] ?? $member[1], $member[2]],
            $members,
            array_keys($members),
        );

        return [
            '{ '.implode('; ', array_map(static fn (array $member): string => $member[0].': '.$member[1][0], $printed)).' }',
            array_merge([], ...array_map(static fn (array $member): array => [...$member[2], ...$member[1][1]], $printed)),
        ];
    }

    /**
     * An index signature's value with each arm of a member it covers that none of its arms admits, so TypeScript
     * finds the member assignable to it.
     *
     * @param  Typed  $signature
     * @param  Typed  $member
     * @return Typed
     */
    private function admitting(array $signature, array $member): array
    {
        $arms = $this->arms($signature);
        $added = array_values(array_filter($this->arms($member), fn (array $arm): bool => ! $this->within([$arm], $arms, false)));

        return $added === [] ? $signature : [
            implode(' | ', array_column([...$arms, ...$added], 0)),
            array_merge([], ...array_column([...$arms, ...$added], 1)),
        ];
    }

    /**
     * A `Record<K, V>`, its key kept, with V merged with a declared record's value or list's element.
     *
     * @param  Typed  $body
     * @param  Typed  $contents
     * @return Typed
     */
    private function valueMerged(array $body, array $contents, string $at): array
    {
        [$key, $value] = TsTypeShape::recordArguments($body[0]) ?? ['', ''];
        $keySlots = array_slice($body[1], 0, $this->count($key));
        [$text, $slots] = $this->merge([$value, array_slice($body[1], count($keySlots))], $contents, $at.'{}');

        return ['Record<'.$key.', '.$text.'>', [...$keySlots, ...$slots]];
    }

    /**
     * A `T[]` list with T merged with a declared list's element or record's value.
     *
     * @param  Typed  $body
     * @param  Typed  $contents
     * @return Typed
     */
    private function elementMerged(array $body, array $contents, string $at): array
    {
        [$text, $slots] = $this->merge([(string) TsTypeShape::elementType($body[0]), $body[1]], $contents, $at.'[]');

        return [TsTypeShape::postfixOperand($text).'[]', $slots];
    }

    /**
     * Arms joined as one union, each once, or the body's own type where they are its arms unchanged.
     *
     * @param  list<Typed>  $arms
     * @param  Typed  $body
     * @return Typed
     */
    private function joined(array $arms, array $body): array
    {
        $unique = [];

        foreach ($arms as $arm) {
            $unique[serialize($arm)] ??= $arm;
        }

        $texts = array_column($unique, 0);
        $text = implode(' | ', $texts);

        return $text === $body[0] ? $body : [$text, array_merge([], ...array_column($unique, 1))];
    }

    /**
     * Each top-level arm of a type beside its share of the slots.
     *
     * @param  Typed  $typed
     * @return list<Typed>
     */
    private function arms(array $typed): array
    {
        $arms = [];
        $at = 0;

        foreach (TsTypeString::splitTopLevelUnion($typed[0]) as $arm) {
            $arms[] = [$arm, array_slice($typed[1], $at, $this->count($arm))];
            $at += $this->count($arm);
        }

        return $arms;
    }

    /**
     * An arm with each read token a class stands behind spelled as that class, so arms compare by identity: a class
     * the body wraps in an `EnumResource` apart from the enum itself.
     *
     * @param  Typed  $arm
     */
    private function identity(array $arm, bool $isBody): string
    {
        $text = '';
        $from = 0;

        foreach ($this->names === [] ? [] : TokenSlots::tokens('classes', $this->names, $arm[0]) as $index => [$token, $offset, $read]) {
            $class = $arm[1][$index] ?? null;

            if ($read && $class !== null) {
                $wrapped = $isBody && in_array($class, $this->bodyWraps, true);
                $text .= substr($arm[0], $from, $offset - $from).'\\'.($wrapped ? 'EnumResource<'.$class.'>' : $class);
                $from = $offset + strlen($token);
            }
        }

        return $text.substr($arm[0], $from);
    }

    /**
     * How many tokens of the names in play a part of a type spells, each of which takes one slot.
     */
    private function count(string $text): int
    {
        return $this->names === [] ? 0 : count(TokenSlots::tokens('classes', $this->names, $text));
    }
}
