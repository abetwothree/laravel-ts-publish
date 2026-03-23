<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * AST node inspection and predicate helpers for resource analysis.
 */
trait InspectsAstNodes
{
    /** @var list<string> */
    protected array $conditionalMethods = [
        'when', 'whenHas', 'whenNotNull', 'whenLoaded',
        'whenCounted', 'whenAggregated', 'whenPivotLoaded', 'whenPivotLoadedAs',
    ];

    /**
     * Check if a static call's first argument is a conditional expression
     * (e.g. $this->whenLoaded('relation'), $this->when(...), etc.).
     */
    protected function hasConditionalArgument(StaticCall $call): bool
    {
        if ($call->isFirstClassCallable()) {
            return false;
        }

        $args = $call->getArgs();

        if (count($args) < 1) {
            return false;
        }

        $inner = $args[0]->value;

        foreach ($this->conditionalMethods as $method) {
            if ($this->isThisMethodCall($inner, $method)) {
                return true;
            }
        }

        return false;
    }

    protected function isThisMethodCall(Expr $expr, string $methodName): bool
    {
        return $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === $methodName;
    }

    protected function isThisPropertyFetch(Expr $expr): bool
    {
        return $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this';
    }

    protected function resolveKeyName(Expr $key): ?string
    {
        if ($key instanceof String_) {
            return $key->value;
        }

        return null;
    }

    protected function resolveStaticCallClassName(StaticCall $call): ?string
    {
        if ($call->class instanceof Name) {
            return $call->class->toString();
        }

        return null; // @codeCoverageIgnore
    }

    protected function isEnumResourceClass(string $fqcn): bool
    {
        return $fqcn === EnumResource::class
            || $fqcn === 'EnumResource'
            || is_a($fqcn, EnumResource::class, true);
    }

    protected function isResourceClass(string $fqcn): bool
    {
        return class_exists($fqcn) && is_a($fqcn, JsonResource::class, true);
    }

    /**
     * Check if an expression is a parent::toArray() call.
     */
    protected function isParentToArrayCall(Expr $expr): bool
    {
        return $expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->class->toLowerString() === 'parent'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'toArray';
    }
}
