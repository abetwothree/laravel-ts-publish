<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * Reads the literal every return of a method spells: a string, as `broadcastAs()` or `getAuthIdentifierName()` names
 * one, or a list of strings, as a `uniqueIds()` override names its columns. It also says whether the method itself
 * returns a literal `null`, as a caster's get() may.
 *
 * Whole literal or nothing: a concatenation folded to its literal prefix ships a wrong Echo key.
 *
 * @internal
 */
final class ReturnLiteralReader
{
    use InspectsAstNodes;

    /**
     * The string literal every return of a method spells, or null for anything else.
     *
     * @param  class-string  $class
     */
    public function stringLiteral(string $class, string $method): ?string
    {
        $literal = $this->literal($class, $method);

        return is_string($literal) ? $literal : null;
    }

    /**
     * The list of string literals every return of a method spells, or null for anything else.
     *
     * @param  class-string  $class
     * @return list<string>|null
     */
    public function strings(string $class, string $method): ?array
    {
        $literal = $this->literal($class, $method);

        return is_array($literal) ? $literal : null;
    }

    /**
     * Whether a method's own body returns a literal `null`: `return null;`, a bare `return;`, an end it can run to, or
     * a `null` arm of a returned ternary, match or `??`. A null held by a value it reads, or by a closure, is not one.
     *
     * @param  class-string  $class
     */
    public function returnsNull(string $class, string $method): bool
    {
        DependencyRecorder::recordClass($class);
        $function = resolve(MethodLocator::class)->locate($class, $method)?->method;

        if ($function?->stmts === null) {
            return false;
        }

        $bare = fn (Node $node): bool => $node instanceof Return_ && $node->expr === null;

        // A declared return type throws where the body would end without a value.
        if ($function->returnType === null
            && ($this->canFinish($function->stmts) || $this->bodyHas($function->stmts, $bare))
        ) {
            return true;
        }

        return array_any($this->collectReturnExpressions($function->stmts), $this->holdsNull(...));
    }

    /**
     * The one literal every return with a value spells; null where the method yields or has no such return, a return
     * spells anything else, or two returns differ.
     *
     * @param  class-string  $class
     * @return string|list<string>|null
     */
    private function literal(string $class, string $method): string|array|null
    {
        // MethodLocator memoizes its parse, which is what records the file, so a warm read would record nothing.
        DependencyRecorder::recordClass($class);
        $stmts = resolve(MethodLocator::class)->locate($class, $method)?->method->stmts ?? [];

        if ($this->yields($stmts)) {
            return null;
        }

        $literals = array_map($this->literalOf(...), $this->collectReturnExpressions($stmts));
        $first = $literals[0] ?? null;

        return array_all($literals, fn (string|array|null $literal): bool => $literal === $first) ? $first : null;
    }

    /**
     * A string literal, or a list literal of string literals with no key and no spread; null for anything else.
     *
     * @return string|list<string>|null
     */
    private function literalOf(Expr $expr): string|array|null
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if (! $expr instanceof Array_) {
            return null;
        }

        $items = [];

        foreach ($expr->items as $item) {
            if ($item->key !== null || $item->unpack || ! $item->value instanceof String_) {
                return null;
            }

            $items[] = $item->value->value;
        }

        return $items;
    }

    /**
     * Whether a returned expression is the literal `null`, or a ternary, match or `??` holding one as an arm.
     */
    private function holdsNull(Expr $returned): bool
    {
        return match (true) {
            $returned instanceof Ternary => ($returned->if !== null && $this->holdsNull($returned->if))
                || $this->holdsNull($returned->else),
            $returned instanceof Match_ => array_any(
                $returned->arms,
                fn (MatchArm $arm): bool => $this->holdsNull($arm->body),
            ),
            $returned instanceof Coalesce => $this->holdsNull($returned->right),
            default => $returned instanceof ConstFetch && $returned->name->toLowerString() === 'null',
        };
    }

    /**
     * Whether a statement list can run to its end: none of its statements always returns, throws or exits.
     *
     * @param  array<Stmt>  $stmts
     */
    private function canFinish(array $stmts): bool
    {
        return array_all($stmts, $this->completes(...));
    }

    /**
     * Whether running a statement can carry on past it: not a return, a throw or an exit, nor an if, a switch or a try
     * whose every branch ends in one. A loop is taken to carry on.
     */
    private function completes(Stmt $stmt): bool
    {
        return match (true) {
            $stmt instanceof Return_ => false,
            $stmt instanceof Expression => ! $stmt->expr instanceof Throw_ && ! $stmt->expr instanceof Exit_,
            $stmt instanceof Block => $this->canFinish($stmt->stmts),
            $stmt instanceof If_ => $stmt->else === null
                || $this->canFinish($stmt->stmts)
                || array_any($stmt->elseifs, fn (ElseIf_ $elseif): bool => $this->canFinish($elseif->stmts))
                || $this->canFinish($stmt->else->stmts),
            $stmt instanceof Switch_ => $this->switchCompletes($stmt),
            $stmt instanceof TryCatch => $this->tryCompletes($stmt),
            default => true,
        };
    }

    /**
     * Whether a switch can carry on past itself: it has no default, its last case runs to its end, or a `break` or
     * `continue` leaves it.
     */
    private function switchCompletes(Switch_ $switch): bool
    {
        $last = end($switch->cases);

        return $last === false
            || ! array_any($switch->cases, fn (Case_ $case): bool => $case->cond === null)
            || $this->canFinish($last->stmts)
            || $this->breaksOut($switch->cases);
    }

    /**
     * Whether a try can carry on past itself: its finally runs to its end, and its body or one of its catches does.
     */
    private function tryCompletes(TryCatch $try): bool
    {
        if ($try->finally !== null && ! $this->canFinish($try->finally->stmts)) {
            return false;
        }

        return $this->canFinish($try->stmts)
            || array_any($try->catches, fn (Catch_ $catch): bool => $this->canFinish($catch->stmts));
    }

    /**
     * Whether a `break` or `continue` among the nodes leaves the switch holding them, past the $depth loops and
     * switches nested between; a nested function-like or class is left unread.
     *
     * @param  array<mixed>  $nodes
     */
    private function breaksOut(array $nodes, int $depth = 0): bool
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof FunctionLike || $node instanceof ClassLike) {
                continue;
            }

            if ($node instanceof Break_ || $node instanceof Continue_) {
                if (($node->num instanceof Int_ ? $node->num->value : 1) > $depth) {
                    return true;
                }

                continue;
            }

            $nested = $node instanceof Switch_ || $node instanceof Foreach_ || $node instanceof For_
                || $node instanceof While_ || $node instanceof Do_ ? $depth + 1 : $depth;

            foreach ($node->getSubNodeNames() as $name) {
                if ($this->breaksOut(is_array($node->{$name}) ? $node->{$name} : [$node->{$name}], $nested)) {
                    return true;
                }
            }
        }

        return false;
    }
}
