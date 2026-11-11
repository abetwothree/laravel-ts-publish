<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold;

/**
 * Decides what each key an array literal writes more than once sends: PHP's order first, then, where `filter()` runs,
 * each merge call lifted over the keys after it, then which keys a removed value or an unwritten path leaves out.
 *
 * Each path keeps the key's slot where its first write on that path landed, so a first write that may not land lets
 * a merge standing before the next write win there.
 *
 * @phpstan-type Outcome = array{at: int|null, source: int|null}
 *
 * An Outcome is one path's slot: `at` where it stands, null where no write set the key; `source` the write whose
 * value it holds, null where that value is removed.
 *
 * @internal
 */
final class LiteralFold
{
    /**
     * Fold a literal's writes into what each key sends, keyed by name in the order the keys are first written.
     *
     * The item loop of a returned or nested array literal records its writes through LiteralWrites, which folds them
     * here and writes each key once; each branch of a body folds on its own.
     *
     * @param  list<KeyWrite>  $writes  every key write of the literal, in item order and, within an item, key order
     * @param  bool  $liftsMerges  whether `filter()` runs over the literal (Reading::filtersBodyOf()); where it does
     *                             not, a merge call's write is one more write in PHP's order
     * @return array<string, FoldedKey>
     */
    public static function fold(array $writes, bool $liftsMerges): array
    {
        $byName = [];

        foreach ($writes as $write) {
            // Where nothing lifts a merge call, PHP appends it under an int key, so it sets no named key.
            if ($liftsMerges || ! $write->fromMerge) {
                $byName[$write->name][] = $write;
            }
        }

        $folded = [];

        foreach ($byName as $name => $keyWrites) {
            $literal = array_values(array_filter($keyWrites, static fn (KeyWrite $write): bool => ! $write->fromMerge));
            $merges = array_values(array_filter($keyWrites, static fn (KeyWrite $write): bool => $write->fromMerge));
            $outcomes = self::liftedOutcomes(self::slotOutcomes($literal), $merges);

            $folded[(string) $name] = self::foldedKey(min(array_map(static fn (KeyWrite $write): int => $write->position, $keyWrites)), $outcomes);
        }

        return $folded;
    }

    /**
     * Step 1 for one key, PHP's order: a write that always sets the key replaces each path's value, one that may leave
     * it unset adds a path with its value, and one whose value may be removed replaces it with that value or nothing.
     *
     * @param  list<KeyWrite>  $writes  one key's writes outside a lifted merge call, in item order
     * @return list<Outcome>
     */
    private static function slotOutcomes(array $writes): array
    {
        $outcomes = [['at' => null, 'source' => null]];

        foreach ($writes as $write) {
            $next = [];

            foreach ($outcomes as $outcome) {
                $at = $outcome['at'] ?? $write->position;

                if ($write->absent) {
                    $next[] = $outcome;
                }

                $next[] = ['at' => $at, 'source' => $write->source];

                if ($write->removed) {
                    $next[] = ['at' => $at, 'source' => null];
                }
            }

            $outcomes = self::distinct($next);
        }

        return $outcomes;
    }

    /**
     * Step 2 for one key, the merge lift: per path, the slot and the merges contend in position order and the first
     * wins. An unwritten slot, a merge that may not run or a merge's removed value lets the next through.
     *
     * @param  list<Outcome>  $slot  the key's step-1 paths
     * @param  list<KeyWrite>  $merges  the key's merge call writes, in item order
     * @return list<Outcome>
     */
    private static function liftedOutcomes(array $slot, array $merges): array
    {
        if ($merges === []) {
            return $slot;
        }

        $lifted = [];

        foreach ($slot as $outcome) {
            $passes = true;

            foreach ($merges as $merge) {
                // The slot stands before this merge, so it wins, its removed value included.
                if ($outcome['at'] !== null && $outcome['at'] < $merge->position) {
                    $lifted[] = $outcome;
                    $passes = false;

                    break;
                }

                $lifted[] = ['at' => $merge->position, 'source' => $merge->source];

                if (! $merge->absent && ! $merge->removed) {
                    $passes = false;

                    break;
                }
            }

            if ($passes) {
                $lifted[] = $outcome;
            }
        }

        return self::distinct($lifted);
    }

    /**
     * Step 3 for one key: the sources its paths can send, and whether a path ends unwritten or removed.
     *
     * @param  list<Outcome>  $outcomes
     */
    private static function foldedKey(int $position, array $outcomes): FoldedKey
    {
        $sources = [];
        $absent = false;
        $removed = false;

        foreach ($outcomes as $outcome) {
            if ($outcome['at'] === null) {
                $absent = true;
            } elseif ($outcome['source'] === null) {
                $removed = true;
            } else {
                $sources[$outcome['source']] = true;
            }
        }

        $sources = array_keys($sources);
        sort($sources);

        return new FoldedKey($position, $sources, $absent, $removed);
    }

    /**
     * Each outcome once. A loose comparison would take a removed value's null source for write 0.
     *
     * @param  list<Outcome>  $outcomes
     * @return list<Outcome>
     */
    private static function distinct(array $outcomes): array
    {
        $kept = [];

        foreach ($outcomes as $outcome) {
            $kept[($outcome['at'] ?? '-').':'.($outcome['source'] ?? '-')] = $outcome;
        }

        return array_values($kept);
    }
}
