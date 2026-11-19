<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * The object or class and the method name a callable array holds, read by its keys 0 and 1 as PHP reads it, so
 * `[1 => 'm', 0 => $this]` runs `$this->m()`; any other key keeps an array from being callable.
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
     * The pair an array literal holds; false where a computed key or an unpacked item leaves its keys open, so it may
     * be one; null where it holds a key besides 0 and 1, or lacks one of them.
     */
    public static function of(Array_ $array): self|false|null
    {
        $slots = [];
        $open = false;

        foreach ($array->items as $item) {
            $key = $item->key;

            if ($item->unpack || ! ($key === null || $key instanceof Int_ || $key instanceof String_)) {
                $open = true;

                continue;
            }

            // PHP keys the slots as it keys the literal: a numeric string as an int, no key as the next free int.
            if ($key !== null) {
                $slots[$key->value] = $item->value;
            } elseif (! $open) {
                $slots[] = $item->value;
            }

            if (array_diff_key($slots, self::KEYS) !== []) {
                return null;
            }
        }

        return match (true) {
            $open => false,
            isset($slots[0], $slots[1]) => new self($slots[0], $slots[1]),
            default => null,
        };
    }
}
