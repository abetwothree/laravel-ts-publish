<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Walks a method-call chain back to its root expression and resolves the root's class.
 *
 * @internal
 */
class CallChainWalker
{
    /** Unwrap MethodCall receivers recursively to the chain's root expression. */
    public function chainRoot(Expr $expr): Expr
    {
        if ($expr instanceof MethodCall) {
            return $this->chainRoot($expr->var);
        }

        return $expr;
    }

    /**
     * Resolve the chain root to a class-string that is_a() $baseClass, or null.
     * Terminal kinds are opt-in so each retrofit matches its site's current semantics exactly.
     *
     * @template T of object
     *
     * @param  class-string<T>  $baseClass
     * @return class-string<T>|null
     */
    public function resolveRootClass(
        Expr $expr,
        string $baseClass,
        bool $allowStaticCall = true,
        bool $allowNew = false,
        bool $allowClassConst = false,
        bool $recordDependency = true,
    ): ?string {
        $fqcn = $this->terminalClassName($this->chainRoot($expr), $allowStaticCall, $allowNew, $allowClassConst);

        if ($fqcn === null || ! class_exists($fqcn) || ! is_a($fqcn, $baseClass, true)) {
            return null;
        }

        if ($recordDependency) {
            DependencyRecorder::recordClass($fqcn);
        }

        /** @var class-string<T> $fqcn */
        return $fqcn;
    }

    /**
     * Read a class name off an opted-in terminal node kind, or null when none match.
     */
    private function terminalClassName(Expr $root, bool $allowStaticCall, bool $allowNew, bool $allowClassConst): ?string
    {
        if ($allowStaticCall && $root instanceof StaticCall && $root->class instanceof Name) {
            return $root->class->toString();
        }

        if ($allowNew && $root instanceof New_ && $root->class instanceof Name) {
            return $root->class->toString();
        }

        if ($allowClassConst
            && $root instanceof ClassConstFetch
            && $root->class instanceof Name
            && $root->name instanceof Identifier
            && $root->name->toString() === 'class'
        ) {
            return $root->class->toString();
        }

        return null;
    }
}
