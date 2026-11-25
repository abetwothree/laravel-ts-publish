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
}
