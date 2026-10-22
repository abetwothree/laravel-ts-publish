<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * Where a body's statements sit: the blocks each statement holds, and whether one node lies within another. The
 * seeding pass, the guard pass, the write scan and the fill scan all place nodes this way.
 *
 * @internal
 */
final class StatementBlocks
{
    /**
     * The statement blocks a statement holds: its branches, a loop's or a bare block's body, a `try`'s blocks and a
     * `switch`'s cases.
     *
     * @return array<array<Stmt>>
     */
    public static function inner(Stmt $stmt): array
    {
        return match (true) {
            $stmt instanceof If_ => [
                $stmt->stmts,
                ...array_map(fn (ElseIf_ $elseif): array => $elseif->stmts, $stmt->elseifs),
                ...($stmt->else === null ? [] : [$stmt->else->stmts]),
            ],
            $stmt instanceof Foreach_, $stmt instanceof For_, $stmt instanceof While_, $stmt instanceof Do_,
            $stmt instanceof Block => [$stmt->stmts],
            $stmt instanceof TryCatch => [
                $stmt->stmts,
                ...array_map(fn (Catch_ $catch): array => $catch->stmts, $stmt->catches),
                ...($stmt->finally === null ? [] : [$stmt->finally->stmts]),
            ],
            $stmt instanceof Switch_ => array_map(fn (Case_ $case): array => $case->stmts, $stmt->cases),
            default => [],
        };
    }

    /**
     * Whether a node lies within another by their file offsets.
     */
    public static function encloses(Node $outer, Node $inner): bool
    {
        return $outer->getStartFilePos() <= $inner->getStartFilePos() && $inner->getEndFilePos() <= $outer->getEndFilePos();
    }
}
