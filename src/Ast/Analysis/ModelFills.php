<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\StatementBlocks;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;

/**
 * The fill scan: the writes in a body that store a value its model's column holds, and the marks a `new` model a local
 * or a parameter default holds takes for the attributes the body fills before Laravel sends it.
 *
 * @phpstan-import-type NamedWrites from VariableWrites
 *
 * @internal
 */
final class ModelFills
{
    /** The Model methods that write attributes, wherever a body calls them. */
    private const array ATTRIBUTE_WRITERS = ['fill', 'forceFill', 'setAttribute', 'setRawAttributes', 'offsetSet'];

    /**
     * Mark the `new` a variable is bound to as holding the attributes its body fills, before a read or after it, but
     * not past a later write in the list holding that write: Laravel then sends what was written. An attribute writer
     * or calls on it as a statement fill every one; a member or key write fills that column.
     *
     * @param  array<Node>  $body
     * @param  NamedWrites  $writes  the body's writes that replace a variable
     * @param  list<ArrowFunction|ClosureExpr>|null  $parked  the body's closures that never run, read here once
     */
    public static function mark(
        Expr $bound,
        string $name,
        array $body,
        array $writes,
        AnalysisScope $scope,
        ?array &$parked = null,
    ): void {
        if (! $bound instanceof New_ || $bound->args !== []) {
            return;
        }

        $from = $bound->getEndFilePos();
        $cuts = [[PHP_INT_MIN, $from]];

        foreach ($writes as [$written, $node, , $kind]) {
            // A by-reference `use` imports the variable; a write inside that closure is a write of its own.
            if ($written === $name && $node->getStartFilePos() > $from
                && $kind !== VariableWrite::MemberOrOffset && $kind !== VariableWrite::InClosure
            ) {
                $cuts[] = [$node->getStartFilePos(), self::listEnd(self::holdingList($body, $node))];
            }
        }

        $models = resolve(ReceiverClassResolver::class)->resolve($bound, $scope)?->models() ?? [];
        $fill = fn (Node $node): true|string|null => self::fills($node, $name, $models, $scope);
        $members = [];

        $parked ??= self::parkedClosures($body);

        if (self::fillsWithin($body, $name, $fill, $cuts, $parked, $members)) {
            $bound->setAttribute(ReceiverClassResolver::HOLDS_ATTRIBUTES, true);
        } elseif ($members !== []) {
            $bound->setAttribute(ReceiverClassResolver::FILLED_MEMBERS, array_values(array_unique($members)));
        }
    }

    /**
     * Mark each parameter default `new` that AnalysisScope::bindUnpassedParameters() bound as holding attributes where
     * the closure's body fills it.
     */
    public static function markDefaults(Expr $closure, AnalysisScope $scope): void
    {
        if (! $closure instanceof ArrowFunction && ! $closure instanceof ClosureExpr) {
            return;
        }

        $body = $closure->getStmts();
        $writes = null;
        $parked = null;

        foreach ($closure->params as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name) && $param->default instanceof New_
                && ($scope->closureParamExprBindings[$param->var->name] ?? null) === $param->default
            ) {
                $writes ??= VariableWrites::of($body, $scope);
                self::mark($param->default, $param->var->name, $body, $writes, $scope, $parked);
            }
        }
    }

    /**
     * The holder and the column a write through a member or a literal key stores into, and whether a key names it;
     * null for any other write.
     *
     * @return array{Expr, string, bool}|null
     */
    public static function writtenMember(Node $node): ?array
    {
        $target = $node instanceof Assign || $node instanceof AssignOp || $node instanceof PreInc
            || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec ? $node->var : null;

        if ($target instanceof PropertyFetch && $target->name instanceof Identifier) {
            return [$target->var, $target->name->toString(), false];
        }

        return $target instanceof ArrayDimFetch && $target->dim instanceof String_
            ? [$target->var, $target->dim->value, true]
            : null;
    }

    /**
     * Whether a write through a member or key of a variable holding models stores a value its column holds, on those
     * models or on the one model each relation of them the write goes through holds.
     *
     * @param  list<class-string>  $models
     */
    public static function keepsModelThrough(Node $node, string $name, array $models, AnalysisScope $scope): bool
    {
        [$holder, $member] = self::writtenMember($node) ?? [null, '', false];
        $relation = $holder instanceof PropertyFetch && $holder->var instanceof Variable && $holder->var->name === $name
            && $holder->name instanceof Identifier ? $holder->name->toString() : null;
        $relations = resolve(ModelAttributeResolver::class);
        $related = $relation === null ? [] : array_map(
            fn (string $model): ?string => $relations->resolveRelation($model, $relation)['modelFqcn'],
            $models,
        );
        $holds = match (true) {
            $holder instanceof Variable && $holder->name === $name => $models,
            $related !== [] && ! in_array(null, $related, true) => $related,
            default => [],
        };

        return self::storesWithinColumn($node, $member, $holds, $scope);
    }

    /**
     * Whether nodes fill every attribute of the model a variable holds, adding each column one fills alone to $members,
     * outside a nested class or function, a closure that never runs or has a variable of that name of its own, and each
     * span cut off: the reads before the model, and those past a later write. A node the source does not place is read.
     *
     * @param  array<mixed>|Node  $nodes
     * @param  Closure(Node): (true|string|null)  $fill  what one node fills
     * @param  list<array{int, int}>  $cuts
     * @param  list<ArrowFunction|ClosureExpr>  $parked  the closures that never run
     * @param  list<string>  $members
     */
    private static function fillsWithin(
        array|Node $nodes,
        string $name,
        Closure $fill,
        array $cuts,
        array $parked,
        array &$members,
    ): bool {
        foreach (is_array($nodes) ? $nodes : [$nodes] as $node) {
            if (is_array($node) && self::fillsWithin($node, $name, $fill, $cuts, $parked, $members)) {
                return true;
            }

            $start = $node instanceof Node ? $node->getStartFilePos() : -1;
            $cut = fn (int $at): bool => array_any($cuts, fn (array $cut): bool => $at > $cut[0] && $at <= $cut[1]);

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_
                || ($start >= 0 && $cut($start) && $cut($node->getEndFilePos()))
                || (($node instanceof ClosureExpr || $node instanceof ArrowFunction)
                    && ! self::sharesVariable($node, $name, $parked))
            ) {
                continue;
            }

            $filled = $start < 0 || $cut($start) ? null : $fill($node);

            if ($filled === true) {
                return true;
            }

            $members = $filled === null ? $members : [...$members, $filled];

            foreach ($node->getSubNodeNames() as $subNode) {
                $child = $node->{$subNode};

                if (($child instanceof Node || is_array($child))
                    && self::fillsWithin($child, $name, $fill, $cuts, $parked, $members)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The statement list holding a node most closely: the innermost block's, else the given list.
     *
     * @param  array<Node>  $stmts
     * @return array<Node>
     */
    private static function holdingList(array $stmts, Node $node): array
    {
        foreach ($stmts as $stmt) {
            $inner = $stmt instanceof Node\Stmt && StatementBlocks::encloses($stmt, $node)
                ? array_find(StatementBlocks::inner($stmt), fn (array $block): bool => array_any(
                    $block,
                    fn (Node\Stmt $held): bool => StatementBlocks::encloses($held, $node),
                ))
                : null;

            if ($inner !== null) {
                return self::holdingList($inner, $node);
            }
        }

        return $stmts;
    }

    /**
     * The offset a statement list ends at; one the source does not place reaches past every offset.
     *
     * @param  array<Node>  $stmts
     */
    private static function listEnd(array $stmts): int
    {
        $end = $stmts === [] ? -1 : $stmts[array_key_last($stmts)]->getEndFilePos();

        return $end < 0 ? PHP_INT_MAX : $end;
    }

    /**
     * What one node fills of the model a variable holds: every attribute (true) where it calls an attribute writer on
     * it, or a chain of methods on it as a statement, such as a relation's associate(); the column a member or key
     * write of it stores a value that column holds in; else nothing.
     *
     * @param  list<class-string<Model>>  $models  the models the variable may hold
     */
    private static function fills(Node $node, string $name, array $models, AnalysisScope $scope): true|string|null
    {
        [$holder, $member] = self::writtenMember($node) ?? [null, '', false];

        return match (true) {
            $holder !== null => $holder instanceof Variable && $holder->name === $name
                && self::storesWithinColumn($node, $member, $models, $scope) ? $member : null,
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $node->name instanceof Identifier
                && in_array($node->name->toString(), self::ATTRIBUTE_WRITERS, true)
                && $node->var instanceof Variable && $node->var->name === $name ?: null,
            default => $node instanceof ExpressionStmt
                && ($node->expr instanceof MethodCall || $node->expr instanceof NullsafeMethodCall)
                && self::callsOn($node->expr->var, $name) ?: null,
        };
    }

    /**
     * Whether a write stores in a column a value that column's published type holds on every model it may be written
     * on: a constant, or what `.=`, an arithmetic operator, `++` or `--` leaves.
     *
     * @param  list<class-string>  $models
     */
    private static function storesWithinColumn(Node $node, string $member, array $models, AnalysisScope $scope): bool
    {
        $value = match (true) {
            $node instanceof Assign, $node instanceof CoalesceAssign => resolve(ValueResolver::class)->constantType($node->expr, $scope),
            $node instanceof AssignOp\Concat => 'string',
            $node instanceof AssignOp, $node instanceof PreInc, $node instanceof PostInc, $node instanceof PreDec,
            $node instanceof PostDec => 'number',
            default => null,
        };
        $attributes = resolve(ModelAttributeResolver::class);

        return $value !== null && $models !== [] && array_all($models, fn (string $model): bool => in_array(
            $value,
            TsTypeString::splitTopLevelUnion($attributes->resolveAttribute($model, $member)['type']),
            true,
        ));
    }

    /**
     * Whether an expression is the variable, or a chain of method calls on it.
     */
    private static function callsOn(Expr $expr, string $name): bool
    {
        while ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === $name;
    }

    /**
     * Whether a nested function shares a variable of the body around it and can run: a closure importing it, or an
     * arrow function with no parameter of its name, neither one that never runs.
     *
     * @param  list<ArrowFunction|ClosureExpr>  $parked
     */
    private static function sharesVariable(ArrowFunction|ClosureExpr $function, string $name, array $parked): bool
    {
        $declares = fn (Param $param): bool => $param->var instanceof Variable && $param->var->name === $name;

        return ! in_array($function, $parked, true) && ($function instanceof ClosureExpr
            ? array_any($function->uses, fn (ClosureUse $use): bool => $use->var->name === $name)
            : ! array_any($function->params, $declares));
    }

    /**
     * The closures and arrow functions a body assigns, as a statement of their own, to a variable it spells nowhere
     * else, which nothing can call.
     *
     * @param  array<Node>  $body
     * @return list<ArrowFunction|ClosureExpr>
     */
    private static function parkedClosures(array $body): array
    {
        $finder = new NodeFinder;
        $spelled = array_count_values(array_map(
            fn (Variable $variable): string => is_string($variable->name) ? $variable->name : '',
            $finder->findInstanceOf($body, Variable::class),
        ));
        $parked = [];

        foreach ($finder->findInstanceOf($body, ExpressionStmt::class) as $stmt) {
            $assign = $stmt->expr;

            if ($assign instanceof Assign
                && ($assign->expr instanceof ClosureExpr || $assign->expr instanceof ArrowFunction)
                && $assign->var instanceof Variable && is_string($assign->var->name)
                && ($spelled[$assign->var->name] ?? 0) === 1
            ) {
                $parked[] = $assign->expr;
            }
        }

        return $parked;
    }
}
