<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold;

use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use PhpParser\Node\Expr\ArrayItem;

/**
 * Which spreads of one nested literal its print keeps as arms, each spread classified once
 * (InlineArrayHandler::planArms()): the items its item loop reads, whether an arm follows each, and the arm each
 * prints as. The item loop records each printed arm in LiteralWrites, whose fold hands the list to the print.
 *
 * @phpstan-import-type InlineSpreadArm from InlineObjectResult
 * @phpstan-import-type ObjectSpreadArm from InlineObjectResult
 *
 * @phpstan-type PrintedArm = array{arm: InlineSpreadArm|ObjectSpreadArm, mapValue: string|null}
 *
 * @internal
 */
final readonly class LiteralArms
{
    /**
     * Hold one literal's plan.
     *
     * @param  list<ArrayItem>  $items  the literal's items, each spread of a literal no later arm follows inlined
     * @param  list<bool>  $follows  per item, whether a later one stays an arm
     * @param  array<int, InlineSpreadArm|ObjectSpreadArm|null>  $arms  per spread a print may keep, the arm it is
     * @param  array<int, PrintedArm>  $printed  per spread the print keeps, its arm, and for a map's after another
     *                                           item, the value each key the fold writes must fit
     */
    public function __construct(
        public array $items,
        public array $follows,
        public array $arms,
        public array $printed,
    ) {}

    /**
     * Whether the plan classified the item at $index, whose arm $arms then holds.
     */
    public function classified(int $index): bool
    {
        return array_key_exists($index, $this->arms);
    }
}
