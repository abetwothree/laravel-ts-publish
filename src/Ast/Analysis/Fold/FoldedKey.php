<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold;

/**
 * What one key of an array literal sends once LiteralFold has applied PHP's order, the merge lift and the removals:
 * the writes whose values can reach the client, and why the key may not.
 *
 * `absent` says no write sets the key on some path, `removed` that the value a winning write sets may be removed; the
 * row the key publishes carries both, as the branch join and the returned variable's walk read them.
 *
 * @internal
 */
final readonly class FoldedKey
{
    /** Whether the key may not be sent: printed `?`. */
    public bool $optional;

    /**
     * @param  int  $position  the item index of the key's first write, which orders it among the literal's keys
     * @param  list<int>  $sources  each recorded source whose value the key can send, in source order
     * @param  bool  $absent  whether some path writes no value to the key
     * @param  bool  $removed  whether some path sends the key a value Laravel or Inertia removes
     */
    public function __construct(
        public int $position,
        public array $sources,
        public bool $absent,
        public bool $removed,
    ) {
        $this->optional = $absent || $removed;
    }
}
