<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold;

/**
 * One key one item of an array literal writes, as LiteralFold reads it: where the item stands, whether it is a
 * merge call's, and the two presence facts of its value.
 *
 * `absent` says the item may leave the key unset, where a value written before it survives; `removed` says the item
 * always sets the key, to a value Laravel or Inertia may remove, which no earlier value survives. Both may hold, as a
 * printed `?` that cannot tell the two apart does. Neither holds for a write that sets the key on every path.
 *
 * @internal
 */
final readonly class KeyWrite
{
    /**
     * @param  string  $name  the key PHP writes
     * @param  int  $position  the item's index in the literal; every key one item writes shares it
     * @param  bool  $fromMerge  whether a `merge()`, `mergeWhen()` or `mergeUnless()` item writes it
     * @param  bool  $absent  whether the item may leave the key unset
     * @param  bool  $removed  whether the value it sets may be removed
     * @param  int  $source  the index of the analysis the value comes from in the literal's recorded sources
     */
    public function __construct(
        public string $name,
        public int $position,
        public bool $fromMerge,
        public bool $absent,
        public bool $removed,
        public int $source,
    ) {}
}
