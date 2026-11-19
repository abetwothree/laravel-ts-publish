<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * The object or class and the method name a callable array holds, read by its keys 0 and 1 as PHP reads it, so
 * `[1 => 'm', 0 => $this]` and `[...[$this, 'm']]` run `$this->m()`; any other key keeps an array from being callable.
 *
 * @phpstan-type Slots = array{array<int|string, Expr>, bool}
 * @phpstan-type Entries = list<array{int|string|null, Expr}>
 *
 * @internal
 */
final readonly class CallablePair
{
    /** The keys a callable array holds, and no other. */
    private const array KEYS = [0 => true, 1 => true];

    /**
     * A pair of the values keyed 0 and 1.
     */
    private function __construct(public Expr $holder, public Expr $method) {}

    /**
     * The pair an array literal holds; false where a computed key or an unpack it cannot spread leaves its keys open,
     * so it may be one; null where it holds a key besides 0 and 1, or lacks one of them.
     */
    public static function of(Array_ $array): self|false|null
    {
        [$slots, $open] = self::slots($array);

        return match (true) {
            array_diff_key($slots, self::KEYS) !== [] => null,
            $open => false,
            isset($slots[0], $slots[1]) => new self($slots[0], $slots[1]),
            default => null,
        };
    }

    /**
     * The values an array literal holds, keyed as PHP keys them, beside whether a computed key or an unpack it cannot
     * spread leaves more keys open.
     *
     * @return Slots
     */
    private static function slots(Array_ $array): array
    {
        $slots = [];
        $open = false;

        foreach ($array->items as $item) {
            $entries = self::entries($item);

            if ($entries === null) {
                $open = true;

                continue;
            }

            // PHP keys the slots as it keys the literal: a numeric string as an int, no key as the next free int.
            foreach ($entries as [$key, $value]) {
                if ($key !== null) {
                    $slots[$key] = $value;
                } elseif (array_key_exists(PHP_INT_MAX, $slots)) {
                    // PHP throws where no int is free.
                    $open = true;
                } elseif (! $open) {
                    $slots[] = $value;
                }
            }
        }

        return [$slots, $open];
    }

    /**
     * The keys and values an item puts in a literal, a key null where PHP takes the next free int: its own, or a
     * spread literal's, whose int keys PHP renumbers; null where a computed key or an unpack it cannot spread leaves
     * them open.
     *
     * @return Entries|null
     */
    private static function entries(ArrayItem $item): ?array
    {
        $key = $item->key;

        if (! $item->unpack) {
            return $key === null || $key instanceof Int_ || $key instanceof String_ ? [[$key?->value, $item->value]] : null;
        }

        [$spread, $open] = $item->value instanceof Array_ ? self::slots($item->value) : [[], true];

        return $open ? null : array_map(
            static fn (int|string $key, Expr $value): array => [is_int($key) ? null : $key, $value],
            array_keys($spread),
            $spread,
        );
    }
}
