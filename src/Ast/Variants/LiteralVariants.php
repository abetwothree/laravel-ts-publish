<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;

/**
 * The groups one array literal's fold holds, beside the writes they were read from, whether `filter()` lifts the
 * literal's merge calls, and every row the fold wrote, which a later rewrite of any row no longer matches.
 *
 * @phpstan-import-type RowFingerprints from VariantGroup
 *
 * @internal
 */
final class LiteralVariants
{
    /**
     * The groups of one literal.
     *
     * @param  list<VariantGroup>  $groups  in the order of their discriminants
     * @param  RowFingerprints  $fingerprint  every row the fold wrote
     */
    public function __construct(
        public readonly array $groups,
        public readonly LiteralWrites $writes,
        public readonly bool $liftsMerges,
        public readonly array $fingerprint,
    ) {}
}
