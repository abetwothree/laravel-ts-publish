<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

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
    /**
     * @param  list<string>  $names  the names whose every token a side's slots give a class, or null
     * @param  list<class-string>  $bodyWraps  the enums the body wraps in an `EnumResource` where it spells them bare
     */
    public function __construct(
        private array $names,
        private array $bodyWraps = [],
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
     * @return Typed
     */
    public function merge(array $body, array $declared): array
    {
        if (self::saysNothing($declared[0])) {
            return $body;
        }

        if (self::saysNothing($body[0])) {
            // `withNullArm()` appends a `null`, which spells no class, so the declared slots still line up.
            return [TsTypeString::withNullArmOf($body[0], $declared[0]), $declared[1]];
        }

        $bodyArms = $this->arms($body);
        $declaredArms = $this->arms($declared);
        $pairs = $this->pairs($bodyArms, $declaredArms);
        $verdict = $this->verdict($bodyArms, $declaredArms, $pairs);

        $merged = [];
        $declaredAtoms = self::atoms($declaredArms);

        foreach ($bodyArms as $index => $arm) {
            if (self::kindOf($arm[0]) !== null) {
                $merged[] = isset($pairs[$index]) ? $this->containerMerged($arm, $declaredArms[$pairs[$index]]) : $arm;
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
     * Whether a type tells nothing of its value but that it may be null: `unknown` or `mixed`.
     */
    private static function saysNothing(string $type): bool
    {
        return TsTypeString::isUnknownOnly($type) || $type === 'mixed';
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
     * Which side decides the arms no container pairs: the declared ones, arm by arm, where they are within the body's,
     * with or without a `null` beside them; else the body's, an `unknown` arm of which takes the declared arms no other
     * body arm covers.
     *
     * @param  list<Typed>  $bodyArms
     * @param  list<Typed>  $declaredArms
     * @param  array<int, int>  $pairs
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
     * @param  array<int, int>  $pairs
     * @return list<Typed>
     */
    private function uncovered(array $bodyArms, array $declaredArms, array $pairs): array
    {
        $typed = array_values(array_filter($bodyArms, static fn (array $arm): bool => $arm[0] !== 'unknown' && self::kindOf($arm[0]) === null));

        return array_values(array_filter(
            $declaredArms,
            fn (array $arm, int $index): bool => self::kindOf($arm[0]) === null
                ? ! $this->within([$arm], $typed, false)
                : ! in_array($index, $pairs, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Each body container arm's index beside the index of the one declared arm of its kind, where exactly one is.
     *
     * @param  list<Typed>  $bodyArms
     * @param  list<Typed>  $declaredArms
     * @return array<int, int>
     */
    private function pairs(array $bodyArms, array $declaredArms): array
    {
        $pairs = [];

        foreach ($bodyArms as $index => [$arm]) {
            $kind = self::kindOf($arm);
            $same = $kind === null ? [] : array_keys(array_filter($declaredArms, static fn (array $declared): bool => self::kindOf($declared[0]) === $kind));

            if (count($same) === 1) {
                $pairs[$index] = $same[0];
            }
        }

        return $pairs;
    }

    /**
     * Two container arms of one kind merged: an object member by member, a list by element, a record by value.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @return Typed
     */
    private function containerMerged(array $body, array $declared): array
    {
        return match (self::kindOf($body[0])) {
            'object' => $this->membersMerged($body, $declared),
            'record' => $this->valueMerged($body, $declared),
            default => $this->elementMerged($body, $declared),
        };
    }

    /**
     * An object type with each member the body sends merged with the declared member of its key, `?` aside, keeping
     * the body's keys, order and optional flags.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @return Typed
     */
    private function membersMerged(array $body, array $declared): array
    {
        $declaredMembers = [];
        $at = 0;

        foreach (TsTypeShape::members($declared[0]) ?? [] as [$key, $value]) {
            $at += $this->count($key);
            $declaredMembers[rtrim($key, '?')] = [$value, array_slice($declared[1], $at, $this->count($value))];
            $at += $this->count($value);
        }

        $members = [];
        $slots = [];
        $at = 0;

        foreach (TsTypeShape::members($body[0]) ?? [] as [$key, $value]) {
            array_push($slots, ...array_slice($body[1], $at, $this->count($key)));
            $at += $this->count($key);
            $read = [$value, array_slice($body[1], $at, $this->count($value))];
            $at += $this->count($value);
            $fill = $declaredMembers[rtrim($key, '?')] ?? null;
            [$text, $valueSlots] = $fill === null ? $read : $this->merge($read, $fill);
            $members[] = $key.': '.$text;
            array_push($slots, ...$valueSlots);
        }

        return ['{ '.implode('; ', $members).' }', $slots];
    }

    /**
     * A `Record<K, V>` with V merged with the declared record's value.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @return Typed
     */
    private function valueMerged(array $body, array $declared): array
    {
        [$key, $value] = TsTypeShape::recordArguments($body[0]) ?? ['', ''];
        [$declaredKey, $declaredValue] = TsTypeShape::recordArguments($declared[0]) ?? ['', 'unknown'];
        $keySlots = array_slice($body[1], 0, $this->count($key));
        [$text, $slots] = $this->merge(
            [$value, array_slice($body[1], count($keySlots))],
            [$declaredValue, array_slice($declared[1], $this->count($declaredKey))],
        );

        return ['Record<'.$key.', '.$text.'>', [...$keySlots, ...$slots]];
    }

    /**
     * A `T[]` list with T merged with the declared list's element.
     *
     * @param  Typed  $body
     * @param  Typed  $declared
     * @return Typed
     */
    private function elementMerged(array $body, array $declared): array
    {
        [$text, $slots] = $this->merge(
            [(string) TsTypeShape::elementType($body[0]), $body[1]],
            [(string) TsTypeShape::elementType($declared[0]), $declared[1]],
        );

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
