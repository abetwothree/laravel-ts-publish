<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Locates a method's Inertia render calls and reads their component names and props arguments.
 */
class InertiaRenderLocator
{
    public function __construct(protected CallMatcher $matcher) {}

    /**
     * Find the first Inertia::render(...) call in a method.
     */
    public function findRenderCall(ClassMethod $method): ?StaticCall
    {
        if ($method->stmts === null) {
            return null;
        }

        /** @var StaticCall|null $call */
        $call = (new NodeFinder)->findFirst(
            $method->stmts,
            fn (Node $node): bool => $this->matcher->isStaticCallTo($node, 'Inertia', 'render'),
        );

        return $call;
    }

    /**
     * Every Inertia render call in a method — `Inertia::render()`, `inertia()`, and
     * `inertia()->render()` — normalized into name/props argument pairs, in source order.
     *
     * @return list<RenderCall>
     */
    public function findRenderCalls(ClassMethod $method): array
    {
        if ($method->stmts === null) {
            return [];
        }

        $nodes = (new NodeFinder)->find(
            $method->stmts,
            fn (Node $node): bool => $this->matcher->isStaticCallTo($node, 'Inertia', 'render')
                || $this->isInertiaHelperCall($node)
                || $this->isInertiaHelperRenderCall($node),
        );

        $calls = [];

        foreach ($nodes as $node) {
            if (! $node instanceof StaticCall && ! $node instanceof FuncCall && ! $node instanceof MethodCall) {
                continue; // @codeCoverageIgnore
            }

            $args = $this->renderArguments($node);

            $calls[] = new RenderCall(
                ($args->named('component') ?? $args->at(0))?->value,
                ($args->named('props') ?? $args->at(1))?->value,
            );
        }

        return $calls;
    }

    /**
     * Resolve the component string from Inertia::render('Component', ...).
     */
    public function componentName(StaticCall $render): ?string
    {
        $args = $this->renderArguments($render);
        $component = ($args->named('component') ?? $args->at(0))?->value;

        return $component instanceof String_ ? $component->value : null;
    }

    /**
     * The raw second-argument expression of Inertia::render(...), whatever its shape.
     */
    public function propsArg(StaticCall $render): ?Expr
    {
        $args = $this->renderArguments($render);

        return ($args->named('props') ?? $args->at(1))?->value;
    }

    /**
     * propsArg() narrowed to an inline array literal.
     */
    public function propsArray(StaticCall $render): ?Array_
    {
        $expr = $this->propsArg($render);

        return $expr instanceof Array_ ? $expr : null;
    }

    /**
     * A render call's arguments mapped against the signature it reaches: ResponseFactory::render() for the
     * facade and helper-chain forms, the inertia() helper for the function form — both name (component, props).
     * Without the adapter installed only positions are known, which the `?? at()` fallbacks above read.
     */
    protected function renderArguments(StaticCall|FuncCall|MethodCall $call): CallArguments
    {
        if ($call instanceof FuncCall && function_exists('inertia')) {
            return CallArguments::for($call, new ReflectionFunction('inertia'));
        }

        // The Inertia adapter is a dev dependency, so it is named by string rather than imported —
        // an import would declare a hard requirement this package does not have.
        $factory = 'Inertia\\ResponseFactory';

        if (! $call instanceof FuncCall && class_exists($factory)) {
            return CallArguments::for($call, new ReflectionMethod($factory, 'render'));
        }

        return CallArguments::fromNames($call->isFirstClassCallable() ? [] : $call->getArgs(), []);
    }

    /**
     * Whether the node is an `inertia('Component', [...])` helper call carrying arguments.
     */
    protected function isInertiaHelperCall(Node $node): bool
    {
        return $node instanceof FuncCall
            && $node->name instanceof Name
            && $node->name->toString() === 'inertia'
            && ! $node->isFirstClassCallable()
            && $node->getArgs() !== [];
    }

    /**
     * Whether the node is an `inertia()->render('Component', [...])` call.
     */
    protected function isInertiaHelperRenderCall(Node $node): bool
    {
        return $node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'render'
            && $node->var instanceof FuncCall
            && $node->var->name instanceof Name
            && $node->var->name->toString() === 'inertia';
    }
}
