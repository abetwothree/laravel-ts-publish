<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\Plus as PlusAssign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * Follows a method's `parent::` calls up its class chain, as far as each body returns the keys its call returns: the
 * call returned whole, spread, merged with array_merge(), added with `+`, or written to a local the body returns.
 *
 * A call under a key nests its keys there, so the chain stops at the body that makes it.
 *
 * @internal
 */
final class ParentCallChain
{
    use InspectsAstNodes;

    /**
     * The classes declaring each body whose keys a call of the method returns, the one the class runs first.
     *
     * @return list<class-string>
     */
    public function declaringClasses(string $class, string $method): array
    {
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return [];
        }

        $declaring = new ReflectionClass($class)->getMethod($method)->getDeclaringClass();
        $chain = [$declaring->getName()];

        while ($this->returnsParentKeys($declaring->getName(), $method)
            && ($parent = $declaring->getParentClass()) !== false
            && $parent->hasMethod($method)
        ) {
            $declaring = $parent->getMethod($method)->getDeclaringClass();
            $chain[] = $declaring->getName();
        }

        return $chain;
    }

    /**
     * Whether the body a class declares for a method returns the keys its parent's declaration returns.
     */
    private function returnsParentKeys(string $class, string $method): bool
    {
        $stmts = resolve(MethodLocator::class)->locate($class, $method)?->method->stmts ?? [];

        return array_any(
            $this->collectReturnExpressions($stmts),
            fn (Expr $returned): bool => $this->holdsParentKeys($returned, $method, $stmts),
        );
    }

    /**
     * Whether an expression's own keys include those a `parent::` call of the method returns.
     *
     * @param  array<Stmt>  $stmts  the body, whose writes a local holds
     * @param  array<string, true>  $followed  the locals already followed, so a write reading its own local ends
     */
    private function holdsParentKeys(Expr $expr, string $method, array $stmts, array $followed = []): bool
    {
        $holds = fn (Expr $part): bool => $this->holdsParentKeys($part, $method, $stmts, $followed);

        return match (true) {
            $expr instanceof StaticCall => $this->isParentCallOf($expr, $method),
            $expr instanceof Array_ => array_any($expr->items, fn (ArrayItem $item): bool => $item->unpack && $holds($item->value)),
            $expr instanceof FuncCall => $this->isArrayMerge($expr)
                && array_any($expr->getArgs(), fn (Arg $arg): bool => $holds($arg->value)),
            $expr instanceof Plus, $expr instanceof Coalesce => $holds($expr->left) || $holds($expr->right),
            $expr instanceof Ternary => $holds($expr->if ?? $expr->cond) || $holds($expr->else),
            $expr instanceof Variable => $this->localHoldsParentKeys($expr, $method, $stmts, $followed),
            default => false,
        };
    }

    /**
     * Whether a write to a local holds the keys a `parent::` call of the method returns.
     *
     * @param  array<Stmt>  $stmts  the body, whose writes the local holds
     * @param  array<string, true>  $followed  the locals already followed
     */
    private function localHoldsParentKeys(Variable $local, string $method, array $stmts, array $followed): bool
    {
        $name = $local->name;

        if (! is_string($name) || isset($followed[$name])) {
            return false;
        }

        return array_any(
            $this->localWrites($stmts, $name),
            fn (Expr $written): bool => $this->holdsParentKeys($written, $method, $stmts, $followed + [$name => true]),
        );
    }

    /**
     * Whether a static call is `parent::{$method}()`, under any casing, as PHP dispatches it.
     */
    private function isParentCallOf(StaticCall $call, string $method): bool
    {
        return $call->class instanceof Name
            && $call->class->toLowerString() === 'parent'
            && $call->name instanceof Identifier
            && $call->name->toLowerString() === strtolower($method);
    }

    /**
     * Whether a function call is array_merge() with its arguments written out.
     */
    private function isArrayMerge(FuncCall $call): bool
    {
        return $call->name instanceof Name
            && strtolower($call->name->getLast()) === 'array_merge'
            && ! $call->isFirstClassCallable();
    }

    /**
     * What the body writes to a local, by `=` or `+=`.
     *
     * @param  array<Stmt>  $stmts
     * @return list<Expr>
     */
    private function localWrites(array $stmts, string $name): array
    {
        $finder = new NodeFinder;
        $writes = [...$finder->findInstanceOf($stmts, Assign::class), ...$finder->findInstanceOf($stmts, PlusAssign::class)];

        return array_values(array_map(
            fn (Assign|PlusAssign $write): Expr => $write->expr,
            array_filter($writes, fn (Assign|PlusAssign $write): bool => $write->var instanceof Variable && $write->var->name === $name),
        ));
    }
}
