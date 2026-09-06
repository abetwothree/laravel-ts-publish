<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CoalesceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * A throwaway scope for tests that never inspect its contents.
 */
function coalesceHandlerTestScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(stdClass::class));
}

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function coalesceHandlerThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

/**
 * A stub engine resolving each distinct expression instance to its own canned result, keyed by
 * object identity, so a two-arm coalesce's left/right recursion can be pinned independently
 * rather than sharing one canned response.
 */
final class CoalesceArmStubEngine implements ExpressionEngine
{
    /** @param list<array{0: Expr, 1: array<string, mixed>}> $arms */
    public function __construct(private array $arms) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        foreach ($this->arms as [$candidate, $result]) {
            if ($candidate === $expr) {
                return $result;
            }
        }

        throw new RuntimeException('Unexpected expression passed to CoalesceArmStubEngine');
    }

    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

// CoalesceHandler

it('unions both arms when they resolve to different real types', function () {
    $handler = new CoalesceHandler;
    $left = new Variable('a');
    $right = new Variable('b');
    $expr = new BinaryOp\Coalesce($left, $right);
    $engine = new CoalesceArmStubEngine([
        [$left, ['type' => 'string', 'optional' => false]],
        [$right, ['type' => 'number', 'optional' => false]],
    ]);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'string | number', 'optional' => false]);
});

it('appends `| null` when the right arm is the bare null constant', function () {
    $handler = new CoalesceHandler;
    $left = new Variable('a');
    $right = new ConstFetch(new Name('null'));
    $expr = new BinaryOp\Coalesce($left, $right);
    $engine = new CoalesceArmStubEngine([
        [$left, ['type' => 'string', 'optional' => false]],
        [$right, ['type' => 'null', 'optional' => false]],
    ]);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'string | null', 'optional' => false]);
});

it('collapses to a single type when both arms resolve identically', function () {
    $handler = new CoalesceHandler;
    $left = new Variable('a');
    $right = new Variable('b');
    $expr = new BinaryOp\Coalesce($left, $right);
    $engine = new CoalesceArmStubEngine([
        [$left, ['type' => 'string', 'optional' => false]],
        [$right, ['type' => 'string', 'optional' => false]],
    ]);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('falls through entirely to the right arm when the left is unknown, propagating its modelFqcn', function () {
    $handler = new CoalesceHandler;
    $left = new Variable('a');
    $right = new Variable('b');
    $expr = new BinaryOp\Coalesce($left, $right);
    $engine = new CoalesceArmStubEngine([
        [$left, ['type' => 'unknown', 'optional' => false]],
        [$right, ['type' => 'SomeModel', 'optional' => false, 'modelFqcn' => stdClass::class]],
    ]);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), $engine);

    expect($result)->toBe([
        'type' => 'SomeModel',
        'optional' => false,
        'embeddedModelFqcns' => [stdClass::class],
    ]);
});

it('falls through entirely to the left arm when the right is unknown, propagating a solo enumFqcn', function () {
    $handler = new CoalesceHandler;
    $left = new Variable('a');
    $right = new Variable('b');
    $expr = new BinaryOp\Coalesce($left, $right);
    $engine = new CoalesceArmStubEngine([
        [$left, ['type' => 'Status', 'optional' => false, 'enumFqcn' => stdClass::class]],
        [$right, ['type' => 'unknown', 'optional' => false]],
    ]);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), $engine);

    expect($result)->toBe([
        'type' => 'Status',
        'optional' => false,
        'enumFqcn' => stdClass::class,
    ]);
});

it('strips a top-level `| null` arm from the left before deciding whether it is unknown', function () {
    // `null ?? $x` — the left operand is exactly `null`, which stripNullArm() reduces to 'unknown',
    // so the right arm is returned alone, matching the runtime evaluation of `null ?? $x` as `$x`.
    $handler = new CoalesceHandler;
    $left = new ConstFetch(new Name('null'));
    $right = new Variable('b');
    $expr = new BinaryOp\Coalesce($left, $right);
    $engine = new CoalesceArmStubEngine([
        [$left, ['type' => 'null', 'optional' => false]],
        [$right, ['type' => 'number', 'optional' => false]],
    ]);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('declines a non-coalesce BinaryOp', function () {
    $handler = new CoalesceHandler;
    $expr = new BinaryOp\Plus(new Variable('a'), new Variable('b'));

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), coalesceHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// KnownFunctionCallHandler

it('resolves a known built-in function call to its reflected return type', function () {
    $handler = new KnownFunctionCallHandler;
    $expr = new FuncCall(new Name('count'), []);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), coalesceHandlerThrowingEngine());

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('declines an unrecognized function name', function () {
    $handler = new KnownFunctionCallHandler;
    $expr = new FuncCall(new Name('definitelyNotARealPhpFunction123'), []);

    $result = $handler->resolve($expr, coalesceHandlerTestScope(), coalesceHandlerThrowingEngine());

    expect($result)->toBeNull();
});
