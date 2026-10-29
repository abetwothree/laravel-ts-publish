<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * One list or object arm of an array's published type, as its named members and the keys it leaves unnamed. A write
 * joins its value into each key it may reach, or makes each member it may remove optional; a list a write may key
 * otherwise also becomes that object, kept beside the list.
 *
 * @phpstan-type Member array{optional: bool, type: string}
 * @phpstan-type ArrayWrite array{path: list<string>, key: string|ElementKey, value: string|null}
 *
 * @internal
 */
final readonly class ArrayArm
{
    /**
     * @param  array<string, Member>  $members  each named key, as printed, in order
     * @param  list<string>  $rest  the union arms an unnamed key holds, a list's elements
     * @param  'number'|'string'|null  $index  the keys left unnamed: a list's are its ints, in order; null for none
     * @param  bool  $listable  whether the class's serializer may send this object arm as a list
     * @param  bool  $ordered  whether the members come in the array's order, which a join or a member read back may not
     * @param  int  $least  the fewest members named by numbers the array holds before a write runs
     */
    private function __construct(
        private bool $list,
        private array $members,
        private array $rest,
        private ?string $index,
        private bool $listable = false,
        private bool $ordered = true,
        private int $least = 0,
    ) {}

    /**
     * The type an array of this type holds once each write may run, in any order and any number of times; null where an
     * arm is no list or object, or a write's path names a member an arm does not always hold.
     *
     * @param  non-empty-list<ArrayWrite>  $writes
     * @param  bool  $reindexes  the class sends the array through a filter that lists one keyed only by numbers
     */
    public static function written(string $type, array $writes, bool $reindexes): ?string
    {
        return self::folded($type, $writes, $reindexes, true);
    }

    /**
     * The type written() answers for a type whose object members come in the order the array holds them, or may not.
     *
     * @param  non-empty-list<ArrayWrite>  $writes
     */
    private static function folded(string $type, array $writes, bool $reindexes, bool $ordered): ?string
    {
        $arms = self::settled($type, $writes, $reindexes, $ordered);

        foreach ($arms ?? [] as $kind => $arm) {
            $listed = $arm->withListedMembers($writes, $reindexes);

            if ($listed === null) {
                return null;
            }

            $arms[$kind] = $listed;
        }

        return $arms === null ? null : self::printedUnion($arms, $writes, $reindexes);
    }

    /**
     * The arms of this type once the writes settle, without the lists the class may send an object arm as, which a
     * member's own writes leave out, since a list holds no member a later path could reach; null where folded() is.
     *
     * @param  non-empty-list<ArrayWrite>  $writes
     * @return array<string, self>|null by kind
     */
    private static function settled(string $type, array $writes, bool $reindexes, bool $ordered): ?array
    {
        $arms = [];

        foreach (TsTypeShape::splitTopLevel($type, ['|']) as $printed) {
            $arm = self::parsed($printed, $reindexes, $ordered);

            if ($arm === null) {
                return null;
            }

            $arms = $arm->joinedInto($arms, $reindexes);
        }

        // A write only widens an arm, so the pass that changes none is the last; the bound stops a run that never ends.
        for ($pass = 0; $pass <= 2 * count($writes) + 2; $pass++) {
            $before = array_map(fn (self $arm): string => $arm->printed(), $arms);

            foreach ($writes as $write) {
                foreach (array_keys($arms) as $kind) {
                    $written = $arms[$kind]->writtenBy($write, $reindexes);

                    if ($written === null) {
                        return null;
                    }

                    // An arm of the same kind admits the one it came from, which a write that never runs leaves.
                    foreach ($written as $arm) {
                        $arms = $arm->kind() === $kind
                            ? [...$arms, $kind => $arm]
                            : $arm->joinedInto($arms, $reindexes);
                    }
                }
            }

            if (array_map(fn (self $arm): string => $arm->printed(), $arms) === $before) {
                return $arms;
            }
        }

        return null;
    }

    /**
     * The arm one printed union arm is: a list, an object literal, a `Record` keyed by number or string, or an object
     * literal intersected with `Record<number, T>`; null for any other type.
     */
    private static function parsed(string $printed, bool $reindexes, bool $ordered): ?self
    {
        $printed = TsTypeShape::isWrapped($printed, '(', ')') ? trim(substr($printed, 1, -1)) : $printed;
        $parts = TsTypeShape::splitTopLevel($printed, ['&']);
        $element = count($parts) === 1 ? TsTypeShape::elementType($printed) : null;

        if ($element !== null) {
            return new self(true, [], self::merged([], $element), 'number');
        }

        $arm = new self(false, [], [], null);

        foreach ($parts as $part) {
            $arm = $arm?->withPart($part);
        }

        return $arm === null ? null : new self(
            false,
            $arm->members,
            $arm->rest,
            $arm->index,
            $arm->canList($reindexes),
            $ordered,
            $arm->held(),
        );
    }

    /**
     * These arms, then each type's union arms, each once, in order, without `never`.
     *
     * @param  list<string>  $arms
     * @return list<string>
     */
    private static function merged(array $arms, string ...$types): array
    {
        foreach ($types as $type) {
            array_push($arms, ...TsTypeShape::splitTopLevel($type, ['|']));
        }

        return array_values(array_diff(array_unique($arms), ['never']));
    }

    /**
     * One union of these types, as a join of values prints it: `null` last, an empty list beside a list left out, and
     * `unknown` taking every other arm in.
     *
     * @param  list<string>  $types
     */
    private static function union(array $types): string
    {
        $arms = self::merged([], ...$types);

        return match (true) {
            in_array('unknown', $arms, true) => 'unknown',
            $arms === [] => 'never',
            default => ValueResult::unionResults(
                array_map(fn (string $arm): array => [...ValueResult::unknown(), 'type' => $arm], $arms),
            )['type'],
        };
    }

    /**
     * The int PHP stores a printed member name as, quoted or bare; null for a name it keeps a string.
     */
    private static function intKey(string $name): ?int
    {
        return PhpArrayKey::storedInt(trim($name, '"\''));
    }

    /**
     * Whether an object's members admit each list the class sends it as: always outside a re-indexing resource; there,
     * where named 0, 1, … and either of one type, each always held below the fewest numbers held, or in an order known
     * to be the array's with those always held first as 0, 1, …, so no removal shifts a later one, and the rest alike.
     *
     * @param  array<string, Member>  $listed  the members a list may hold, in the order the array holds them
     */
    private static function holdsLists(array $listed, bool $reindexes, bool $ordered, int $fewest): bool
    {
        if (! $reindexes) {
            return true;
        }

        $ints = array_map(self::intKey(...), array_keys($listed));
        $sorted = $ints;
        sort($sorted);
        $kept = array_filter($listed, fn (array $member): bool => ! $member['optional']);

        if ($sorted !== array_keys($sorted)) {
            return false;
        }

        if (count(array_unique(array_column($listed, 'type'))) <= 1) {
            return array_all(array_keys($kept), fn (string $name): bool => self::intKey($name) < $fewest);
        }

        $always = array_values($kept);
        $others = array_diff_key($listed, $kept);
        $leading = array_slice(array_values($listed), 0, count($always)) === $always
            && array_slice($ints, 0, count($always)) === array_keys($always);

        return $ordered && $leading && (count($others) <= 1 || count(array_unique(array_column($others, 'type'))) <= 1);
    }

    /**
     * Each arm printed, once, an intersection parenthesized beside another arm, and the list the class sends the object
     * arm as where it may, unless the list arm already holds each value that list may hold.
     *
     * @param  array<string, self>  $arms  by kind
     * @param  list<ArrayWrite>|null  $writes  the writes the arms settled under; null leaves out the list
     */
    private static function printedUnion(array $arms, ?array $writes, bool $reindexes): string
    {
        $listed = isset($arms['object']) && $writes !== null
            ? $arms['object']->listedValues($writes, $reindexes)
            : null;
        $printed = array_map(fn (self $arm): string => $arm->printed(), array_values($arms));
        $held = $listed === null || (isset($arms['list']) && array_diff($listed, $arms['list']->rest) === []);
        $printed = array_values(array_unique($held ? $printed : [...$printed, TsTypeShape::listType($listed)]));

        return count($printed) === 1 ? $printed[0] : implode(' | ', array_map(
            fn (string $arm): string => count(TsTypeShape::splitTopLevel($arm, ['&'])) > 1 ? '('.$arm.')' : $arm,
            $printed,
        ));
    }

    /**
     * This object arm with one more part of an intersection read into it: an object literal's members and index
     * signature, or a `Record`'s index; null where the part is neither, or an index of another key type.
     */
    private function withPart(string $part): ?self
    {
        $record = TsTypeShape::recordArguments($part);
        $members = $record === null ? TsTypeShape::members($part) : [['[key: '.$record[0].']', $record[1]]];
        $arm = $this;

        foreach ($members ?? [] as [$key, $type]) {
            $index = preg_match('/^\[\s*\w+\s*:\s*(number|string)\s*\]$/', $key, $match) === 1 ? $match[1] : null;
            $optional = str_ends_with($key, '?');

            if (str_starts_with($key, '[') && ($index === null || ($arm->index ?? $index) !== $index)) {
                return null;
            }

            $arm = $index === null
                ? new self(false, [...$arm->members, ($optional ? substr($key, 0, -1) : $key) => [
                    'optional' => $optional,
                    'type' => $type,
                ]], $arm->rest, $arm->index)
                : new self(false, $arm->members, self::merged($arm->rest, $type), $index);
        }

        return $members === null ? null : $arm;
    }

    /**
     * The arms this one may hold once a write runs on it; null where the write's path names a member this arm does
     * not always hold.
     *
     * @param  ArrayWrite  $write
     * @return list<self>|null
     */
    private function writtenBy(array $write, bool $reindexes): ?array
    {
        if ($write['path'] !== []) {
            $name = JsEmitter::validJsObjectKey($write['path'][0]);
            $member = $this->members[$name] ?? null;
            $inner = [...$write, 'path' => array_slice($write['path'], 1)];
            $arms = $member === null || $member['optional']
                ? null
                : self::settled($member['type'], [$inner], $reindexes, false);

            if ($arms === null) {
                return null;
            }

            $type = self::printedUnion($arms, null, $reindexes);

            return [$this->withMembers([...$this->members, $name => ['optional' => false, 'type' => $type]])];
        }

        if (! $this->list) {
            return [$this->setOrRemoved($write['key'], $write['value'], $reindexes)];
        }

        $key = $write['key'];
        $named = is_string($key) && PhpArrayKey::storedInt($key) === null;
        $object = new self(false, [], $this->rest, 'number', true)->setOrRemoved($key, $write['value'], $reindexes);
        // An append or a rewrite keeps a list's keys in order, and so does an unset of a string key no list holds.
        $keyed = $key !== ElementKey::Next && $key !== ElementKey::Every && ($write['value'] !== null || ! $named);

        return [new self(true, [], $object->rest, 'number'), ...($keyed ? [$object] : [])];
    }

    /**
     * This object arm once a write sets the key it names to a value, or removes it where the value is null, which may
     * leave it a list.
     */
    private function setOrRemoved(string|ElementKey $key, ?string $value, bool $reindexes): self
    {
        // PHP stores a key spelling an int as one, which a number index already holds.
        $key = is_string($key) && $this->index === 'number' && PhpArrayKey::storedInt($key) !== null
            ? ElementKey::Next
            : $key;
        $members = $this->members;
        $named = match (true) {
            is_string($key) => [JsEmitter::validJsObjectKey($key)],
            $key === ElementKey::Next => [],
            default => array_keys($members),
        };

        foreach ($named as $name) {
            $held = $members[$name] ?? null;

            if ($value !== null) {
                $members[$name] = $held === null
                    ? ['optional' => true, 'type' => $value]
                    : [...$held, 'type' => self::union([$held['type'], $value])];
            } elseif ($held !== null) {
                $members[$name] = [...$held, 'optional' => true];
            }
        }

        // Only a set adds a key no member names: an append under a number index, any key under a string one.
        $index = match (true) {
            $value === null || is_string($key) || $key === ElementKey::Every => $this->index,
            $key === ElementKey::Next => $this->index ?? 'number',
            default => 'string',
        };
        $rest = $value === null || $index === null || is_string($key) ? $this->rest : self::merged($this->rest, $value);
        $listable = $this->listable
            || ($value === null && new self(false, $members, $rest, $index)->canList($reindexes));

        return new self(false, $members, $rest, $index, $listable, $this->ordered, $this->least);
    }

    /**
     * The map of arms by kind with this one joined into the arm of its kind.
     *
     * @param  array<string, self>  $arms
     * @return array<string, self>
     */
    private function joinedInto(array $arms, bool $reindexes): array
    {
        $held = $arms[$this->kind()] ?? null;

        return [...$arms, $this->kind() => $held === null ? $this : $held->joinedWith($this, $reindexes)];
    }

    /**
     * This arm joined with another of its kind: each key either holds, optional where one lacks it, the wider index,
     * every unnamed arm, a list where either may be one, and this arm's order, kept only where the other's agrees; in a
     * re-indexing resource an object keyed only by numbers joins as a number index, and elsewhere keeps its named keys.
     */
    private function joinedWith(self $other, bool $reindexes): self
    {
        $mine = $reindexes ? $this->indexed() : $this;
        $theirs = $reindexes ? $other->indexed() : $other;
        $members = [];

        foreach (array_keys([...$mine->members, ...$theirs->members]) as $name) {
            $member = $mine->members[$name] ?? null;
            $joined = $theirs->members[$name] ?? null;
            $members[$name] = [
                'optional' => $member === null || $joined === null || $member['optional'] || $joined['optional'],
                'type' => self::union(array_column(array_filter([$member, $joined]), 'type')),
            ];
        }

        $index = in_array('string', [$mine->index, $theirs->index], true) ? 'string' : ($mine->index ?? $theirs->index);
        $rest = self::merged($mine->rest, ...$theirs->rest);
        $ordered = $mine->ordered && $theirs->ordered && $theirs->keepsOrder(array_keys($members));
        $listable = $mine->listable || $theirs->listable;

        return new self($this->list, $members, $rest, $index, $listable, $ordered, min($mine->least, $theirs->least));
    }

    /**
     * This arm with an object literal keyed only by numbers read as a number index, which a joined arm needs to admit
     * the list a re-indexing resource sends it as; any other arm as it is.
     */
    private function indexed(): self
    {
        $numbers = $this->members !== [] && $this->index !== 'string'
            && array_all(array_keys($this->members), TsTypeShape::isNumericKey(...));
        $rest = self::merged($this->rest, ...array_column($this->members, 'type'));

        return $numbers ? new self(false, [], $rest, 'number', $this->listable, $this->ordered, $this->least) : $this;
    }

    /**
     * Whether this arm's members named by numbers come in the order these names hold them, the one a list follows.
     *
     * @param  list<string>  $names
     */
    private function keepsOrder(array $names): bool
    {
        $numbered = array_values(array_filter(array_keys($this->members), TsTypeShape::isNumericKey(...)));

        return array_values(array_intersect($names, $numbered)) === $numbered;
    }

    /**
     * Whether the class's serializer can send an array holding only this arm's members it always holds as a list: a
     * re-indexing resource where each is named by a number, any other class where each is a key a list may hold.
     */
    private function canList(bool $reindexes): bool
    {
        $kept = array_filter($this->members, fn (array $member): bool => ! $member['optional']);

        // By key, since PHP stores a bare number's name as an int key.
        return array_diff_key($kept, array_flip($this->listedNames($reindexes))) === [];
    }

    /**
     * The members a list may hold: in a re-indexing resource each named by a number; elsewhere each named by an int,
     * beside a string index that may fill any gap, or else only those keyed 0, 1, … without one.
     *
     * @return list<string>
     */
    private function listedNames(bool $reindexes): array
    {
        $numbered = array_values(array_filter(
            array_keys($this->members),
            fn (string $name): bool => $reindexes ? TsTypeShape::isNumericKey($name) : self::intKey($name) !== null,
        ));

        if ($reindexes || ($this->index === 'string' && $this->rest !== [])) {
            return $numbered;
        }

        $ints = array_map(self::intKey(...), $numbered);

        return array_values(array_filter(
            $numbered,
            fn (string $name): bool => array_diff(range(0, max(0, self::intKey($name) ?? 0)), $ints) === [],
        ));
    }

    /**
     * The values of the list the class may send this object arm as, outside a number index, which admits one already:
     * each a string index or a member a list may hold holds, none where a removal may leave it no key; null where the
     * class never sends it as a list.
     *
     * @param  list<ArrayWrite>  $writes
     * @return list<string>|null
     */
    private function listedValues(array $writes, bool $reindexes): ?array
    {
        $index = $this->rest === [] ? null : $this->index;

        if (! $this->listable || $index === 'number') {
            return null;
        }

        $listed = array_intersect_key($this->members, array_flip($this->listedNames($reindexes)));
        $values = $index === 'string' || ! self::holdsLists($listed, $reindexes, $this->ordered, $this->fewest($writes))
            ? self::merged($index === 'string' ? $this->rest : [], ...array_column($listed, 'type'))
            : [];
        $required = array_any($this->members, fn (array $member): bool => ! $member['optional']);
        $removes = array_any($writes, fn (array $write): bool => $write['value'] === null);

        return $values === [] && (! $removes || $required) ? null : $values;
    }

    /**
     * The fewest members named by numbers the array may hold once these writes run: those it held before, less one for
     * each such key an unset names, and those it always holds where an unset's key is computed.
     *
     * @param  list<ArrayWrite>  $writes
     */
    private function fewest(array $writes): int
    {
        $removed = [];

        foreach ($writes as $write) {
            if ($write['path'] !== [] || $write['value'] !== null) {
                continue;
            }

            if (! is_string($write['key'])) {
                return $this->held();
            }

            $removed[$write['key']] = TsTypeShape::isNumericKey($write['key']);
        }

        return max($this->held(), $this->least - count(array_filter($removed)));
    }

    /**
     * How many members named by numbers this arm always holds.
     */
    private function held(): int
    {
        return count(array_filter(
            $this->members,
            fn (array $member, string $name): bool => ! $member['optional'] && TsTypeShape::isNumericKey($name),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * The object arm with each member a write's path reaches folded again with the lists its writes may leave.
     *
     * @param  non-empty-list<ArrayWrite>  $writes
     */
    private function withListedMembers(array $writes, bool $reindexes): ?self
    {
        $members = $this->members;

        foreach ($members as $name => $member) {
            $reaching = array_values(array_filter(
                $writes,
                fn (array $write): bool => $write['path'] !== []
                    && JsEmitter::validJsObjectKey($write['path'][0]) === (string) $name,
            ));

            if ($reaching === []) {
                continue;
            }

            // A member's type is read back from its printed join, which keeps no order, so it is read unordered.
            $type = self::folded($member['type'], array_map(
                fn (array $write): array => [...$write, 'path' => array_slice($write['path'], 1)],
                $reaching,
            ), $reindexes, false);

            if ($type === null) {
                return null;
            }

            $members[$name] = [...$member, 'type' => $type];
        }

        return $this->withMembers($members);
    }

    /**
     * This arm with these members, which keeps what else it holds.
     *
     * @param  array<string, Member>  $members
     */
    private function withMembers(array $members): self
    {
        return new self(
            $this->list,
            $members,
            $this->rest,
            $this->index,
            $this->listable,
            $this->ordered,
            $this->least,
        );
    }

    /**
     * Whether this arm is a union's list or its object, of which it holds one each at most.
     */
    private function kind(): string
    {
        return $this->list ? 'list' : 'object';
    }

    /**
     * This arm as a TypeScript type: a list, an object literal with any string index as a signature that covers each
     * member, a number index as `Record<number, T>` beside it, or the empty list an object with no key is.
     */
    private function printed(): string
    {
        if ($this->list) {
            return TsTypeShape::listType($this->rest);
        }

        $members = $this->members;
        $rest = $this->rest;
        $index = $rest === [] ? null : $this->index;

        // A member keyed by a number is one of the number index's, which would otherwise need both its types at once.
        foreach ($index === 'number' ? $members : [] as $name => $member) {
            if (TsTypeShape::isNumericKey($name)) {
                $rest = self::merged($rest, $member['type']);
                unset($members[$name]);
            }
        }

        $printed = array_map(
            fn (string $name, array $member): string => $name.($member['optional'] ? '?' : '').': '.$member['type'],
            array_keys($members),
            $members,
        );

        if ($index === 'string') {
            $covered = self::union([...array_column($members, 'type'), ...$rest]);
            $covered = array_any($members, fn (array $member): bool => $member['optional'])
                ? TsTypeString::orUndefined($covered)
                : $covered;

            return $members === []
                ? 'Record<string, '.$covered.'>'
                : '{ '.implode('; ', [...$printed, '[key: string]: '.$covered]).' }';
        }

        $object = $members === [] ? null : '{ '.implode('; ', $printed).' }';
        $record = $index === 'number' ? 'Record<number, '.self::union($rest).'>' : null;

        return $object !== null && $record !== null ? $object.' & '.$record : $object ?? $record ?? 'never[]';
    }
}
