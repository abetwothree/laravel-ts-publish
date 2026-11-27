<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use PhpParser\Node;

/**
 * How an exclusive join reads its branches: which untyped values it drops, and where a dropped one leaves a gap.
 *
 * @internal
 */
final readonly class JoinOptions
{
    /**
     * Hold which untyped values a join drops, and where a dropped one leaves a gap.
     *
     * @param  bool  $dropsUntypedBranches  a spread helper's, a merge call's and a literal key's join drops an untyped
     *                                      branch beside a typed one
     * @param  bool  $keepsLoneNull  a merge call keeps the `null` left alone once the untyped branches are gone
     * @param  bool  $keepsFirstUntyped  a literal's key keeps `unknown` where its first source is untyped
     * @param  list<Node>  $returns  each branch's returned value, where the branches are a method's returns
     * @param  AnalysisScope|null  $scope  the scope a gap the join leaves is recorded against
     */
    public function __construct(
        public bool $dropsUntypedBranches = false,
        public bool $keepsLoneNull = false,
        public bool $keepsFirstUntyped = false,
        public array $returns = [],
        public ?AnalysisScope $scope = null,
    ) {}

    /**
     * How `+` and `+=` join a key the left may leave unset: an untypable value the right lays drops beside the left's
     * typed one, an untypable left value keeps the key `unknown`, and a lone `null` stays.
     */
    public static function union(): self
    {
        return new self(dropsUntypedBranches: true, keepsLoneNull: true, keepsFirstUntyped: true);
    }
}
