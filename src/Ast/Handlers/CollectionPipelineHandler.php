<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionFunction;
use ReflectionMethod;

/**
 * A collection chain rooted at `collect($arg)`: the element type comes from `$arg`, survives
 * `values`/`all`/`filter`/`reject`/`unique` plus at most one `map(closure)`, and is array-wrapped back out.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CollectionPipelineHandler implements ExpressionHandler
{
    use SpellsKeyedCollections;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof MethodCall) {
            return null;
        }

        // Walk down the chain collecting op names until we reach the root call.
        /** @var list<array{name: string, node: MethodCall}> $ops */
        $ops = [];
        $node = $expr;

        while ($node instanceof MethodCall) {
            if (! $node->name instanceof Identifier) {
                return null;
            }

            $ops[] = ['name' => $node->name->toString(), 'node' => $node];
            $node = $node->var;
        }

        $rootResult = $this->collectRootResult($node, $engine);

        if ($rootResult === null) {
            return null;
        }

        $element = $this->elementTypeOf($rootResult['type']);

        if ($element === null) {
            return null;
        }

        $identityOps = ['values', 'all', 'filter', 'reject', 'unique'];
        $mapNode = null;

        // collect() over a list starts keyed 0..n-1; each op below says whether that still holds.
        $sequentialKeys = true;

        foreach (array_reverse($ops) as $op) {
            if (in_array($op['name'], $identityOps, true)) {
                $sequentialKeys = match ($op['name']) {
                    'values' => true,
                    'all' => $sequentialKeys,
                    default => false,
                };

                continue;
            }

            if ($op['name'] === 'map' && $mapNode === null) {
                // map() preserves the receiver's keys, so it neither breaks nor restores sequentiality.
                $mapNode = $op['node'];

                continue;
            }

            // Unsupported op, including a second map().
            return null;
        }

        if ($mapNode === null) {
            return [
                ...$rootResult,
                'type' => $sequentialKeys ? $rootResult['type'] : $this->keyedObjectArm($rootResult['type']),
                'optional' => false,
            ];
        }

        $bodyResult = $this->resolveMapBody($mapNode, $element, $scope, $engine);

        if ($bodyResult === null || $bodyResult['type'] === 'unknown') {
            return null;
        }

        $mapped = ValueResult::arrayWrapType($bodyResult['type']);

        return [
            ...$bodyResult,
            'type' => $sequentialKeys ? $mapped : $this->keyedObjectArm($mapped),
            'optional' => false,
        ];
    }

    /**
     * Resolve the argument of a `collect($arg)` root, or decline a chain rooted at anything else.
     *
     * @return ValueExpressionResult|null
     */
    private function collectRootResult(Expr $root, ExpressionEngine $engine): ?array
    {
        if (! $root instanceof FuncCall
            || ! $root->name instanceof Name
            || $root->name->getLast() !== 'collect'
            || $root->isFirstClassCallable()
        ) {
            return null;
        }

        $argument = CallArguments::for($root, new ReflectionFunction('collect'))->named('value')?->value;

        return $argument === null ? null : $engine->resolve($argument);
    }

    /**
     * The element type behind an array type: one trailing `[]` stripped.
     *
     * A top-level `|` leaves it ambiguous which arm the elements came from, so it declines instead.
     */
    private function elementTypeOf(string $type): ?string
    {
        if (! str_ends_with($type, '[]') || count(TsTypeString::splitTopLevelUnion($type)) !== 1) {
            return null;
        }

        return substr($type, 0, -2);
    }

    /**
     * Resolve a `map()` closure body with its first parameter bound to the pipeline's element type.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveMapBody(MethodCall $mapNode, string $element, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $mapArg = CallArguments::for($mapNode, new ReflectionMethod(Collection::class, 'map'))->named('callback')?->value;

        // A callable-array or a bare string callable is itself a resolvable expression, so the engine
        // would answer with *that* — 'strtoupper' → 'string', wrongly wrapped here to 'string[]'.
        if (! $mapArg instanceof ArrowFunction && ! $mapArg instanceof ClosureExpr) {
            return null;
        }

        $previousVarValueBindings = $scope->varValueBindings;

        try {
            if ($mapArg->params !== []
                && $mapArg->params[0]->var instanceof Variable
                && is_string($mapArg->params[0]->var->name)
            ) {
                $scope->varValueBindings[$mapArg->params[0]->var->name] = ['type' => $element, 'optional' => false];
            }

            return $engine->resolve($mapArg);
        } finally {
            $scope->varValueBindings = $previousVarValueBindings;
        }
    }
}
