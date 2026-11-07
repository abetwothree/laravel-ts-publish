<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * Fills the `unknown` positions a type holds, a whole value, an object member, a list element or a record value, from a
 * declared type at the same position, and narrows a bare scalar to the declared literals of that scalar, which a body
 * widens; every other position the type itself types stays as it is.
 *
 * Beside the result it gives the class behind each token of $names, in print order: a filled part's from the declared
 * type's slots, any other part's from the class the body holds under that name.
 *
 * @phpstan-import-type Slots from TokenSlots
 *
 * @phpstan-type Filled = array{string, Slots}
 *
 * @internal
 */
final readonly class UnknownPositions
{
    /**
     * @param  list<string>  $names  the names whose every token the result gives a class, or null
     * @param  list<string>  $declaredOwn  the names whose tokens take the declared type's slots, one each
     * @param  array<string, class-string>  $bodyClasses  the class behind each name's token in a part the body typed
     */
    public function __construct(
        private array $names,
        private array $declaredOwn,
        private array $bodyClasses,
    ) {}

    /**
     * $type with each position it holds as `unknown`, or as a bare scalar $declared spells as literals of, taken from
     * $declared at that position. An arm takes the one arm of $declared, `null` aside, of its own kind: an object, a
     * `T[]` list or a Record.
     *
     * @param  Slots  $slots  the class behind each token of the declared own names $declared spells
     * @return Filled
     */
    public function filled(string $type, string $declared, array $slots): array
    {
        if (self::narrows($type, $declared)) {
            return [$declared, $this->declaredSlots($declared, $slots)];
        }

        if (TsTypeString::isUnknownOnly($type)) {
            return TsTypeString::isUnknownOnly($declared)
                ? $this->body($type)
                : [TsTypeString::withNullArmOf($type, $declared), $this->declaredSlots($declared, $slots)];
        }

        $declaredArms = [];
        $at = 0;

        foreach (TsTypeString::splitTopLevelUnion($declared) as $arm) {
            $count = $this->ownCount($arm);
            $declaredArms = $arm === 'null' ? $declaredArms : [...$declaredArms, [$arm, array_slice($slots, $at, $count)]];
            $at += $count;
        }

        $arms = TsTypeString::splitTopLevelUnion($type);

        if (count($arms) > 1) {
            $parts = array_map(function (string $arm) use ($declaredArms): array {
                $match = $this->armOfKind($arm, $declaredArms);

                return $match === null ? $this->body($arm) : $this->filled($arm, $match[0], $match[1]);
            }, $arms);
            $texts = array_column($parts, 0);

            return $texts === $arms ? $this->body($type) : [implode(' | ', $texts), array_merge([], ...array_column($parts, 1))];
        }

        $match = $this->armOfKind($type, $declaredArms);

        return match (true) {
            $match === null => $this->body($type),
            TsTypeShape::members($type) !== null => $this->membersFilled($type, $match),
            TsTypeShape::recordArguments($type) !== null => $this->recordFilled($type, $match),
            default => $this->elementFilled($type, $match),
        };
    }

    /**
     * Whether a type is a bare scalar each arm of a declared type spells a literal of, as `'a' | 'b'` does `string`:
     * a narrower reading of the same values, never one of another scalar or beside `null`.
     */
    private static function narrows(string $type, string $declared): bool
    {
        $literal = match ($type) {
            'string' => static fn (string $arm): bool => strlen($arm) >= 2 && in_array($arm[0], ['"', "'"], true) && $arm[-1] === $arm[0],
            'number' => static fn (string $arm): bool => is_numeric($arm),
            'boolean' => static fn (string $arm): bool => in_array($arm, ['true', 'false'], true),
            default => null,
        };

        return $literal !== null && array_all(TsTypeString::splitTopLevelUnion($declared), $literal);
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

    /**
     * An object type with each member's `unknown` positions filled from $declared's member of that key, `?` aside.
     *
     * @param  Filled  $declared
     * @return Filled
     */
    private function membersFilled(string $object, array $declared): array
    {
        $declaredMembers = [];
        $at = 0;

        foreach (TsTypeShape::members($declared[0]) ?? [] as [$key, $value]) {
            $at += $this->ownCount($key);
            $count = $this->ownCount($value);
            $declaredMembers[rtrim($key, '?')] = [$value, array_slice($declared[1], $at, $count)];
            $at += $count;
        }

        $members = [];
        $slots = [];
        $changed = false;

        foreach (TsTypeShape::members($object) ?? [] as [$key, $value]) {
            $fill = $declaredMembers[rtrim($key, '?')] ?? null;
            [$filled, $valueSlots] = $fill === null ? $this->body($value) : $this->filled($value, $fill[0], $fill[1]);
            $changed = $changed || $filled !== $value;
            $members[] = $key.': '.$filled;
            array_push($slots, ...$this->bodySlots($key), ...$valueSlots);
        }

        return $changed ? ['{ '.implode('; ', $members).' }', $slots] : $this->body($object);
    }

    /**
     * A `Record<K, V>` with V's `unknown` positions filled from $declared's value.
     *
     * @param  Filled  $declared
     * @return Filled
     */
    private function recordFilled(string $record, array $declared): array
    {
        [$key, $value] = TsTypeShape::recordArguments($record) ?? ['', ''];
        [$declaredKey, $declaredValue] = TsTypeShape::recordArguments($declared[0]) ?? ['', 'unknown'];
        [$filled, $slots] = $this->filled($value, $declaredValue, array_slice($declared[1], $this->ownCount($declaredKey)));

        return $filled === $value ? $this->body($record) : ['Record<'.$key.', '.$filled.'>', [...$this->bodySlots($key), ...$slots]];
    }

    /**
     * A `T[]` list with T's `unknown` positions filled from $declared's element.
     *
     * @param  Filled  $declared
     * @return Filled
     */
    private function elementFilled(string $list, array $declared): array
    {
        $element = (string) TsTypeShape::elementType($list);
        [$filled, $slots] = $this->filled($element, (string) TsTypeShape::elementType($declared[0]), $declared[1]);

        return $filled === $element ? $this->body($list) : [TsTypeShape::postfixOperand($filled).'[]', $slots];
    }

    /**
     * The one arm of $arms of $arm's kind; null where none or several are, or $arm is of no kind kindOf() reads.
     *
     * @param  list<Filled>  $arms
     * @return Filled|null
     */
    private function armOfKind(string $arm, array $arms): ?array
    {
        $kind = self::kindOf($arm);
        $matches = $kind === null ? [] : array_values(array_filter($arms, static fn (array $other): bool => self::kindOf($other[0]) === $kind));

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * A part the body typed, beside the class behind each token of $names it spells.
     *
     * @return Filled
     */
    private function body(string $text): array
    {
        return [$text, $this->bodySlots($text)];
    }

    /**
     * The class behind each token of $names a part the body typed spells: the class the body holds under that name
     * where the token is read, else null.
     *
     * @return Slots
     */
    private function bodySlots(string $text): array
    {
        return array_map(
            fn (array $token): ?string => $token[2] ? $this->bodyClasses[$token[0]] ?? null : null,
            $this->names === [] ? [] : TokenSlots::tokens('classes', $this->names, $text),
        );
    }

    /**
     * The class behind each token of $names a part of the declared type spells: its slot for a token of a declared own
     * name, else null.
     *
     * @param  Slots  $slots
     * @return Slots
     */
    private function declaredSlots(string $text, array $slots): array
    {
        $at = 0;
        $read = [];

        foreach ($this->names === [] ? [] : TokenSlots::tokens('classes', $this->names, $text) as [$token]) {
            $read[] = in_array($token, $this->declaredOwn, true) ? $slots[$at++] ?? null : null;
        }

        return $read;
    }

    /**
     * How many slots a part of the declared type takes: one per token of a declared own name it spells.
     */
    private function ownCount(string $text): int
    {
        return $this->declaredOwn === [] ? 0 : count(TokenSlots::tokens('classes', $this->declaredOwn, $text));
    }
}
