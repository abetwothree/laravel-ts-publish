<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClosureHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Return_;

/**
 * A throwaway scope for tests that never inspect its own subject/model.
 */
function closureHandlerTestScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(stdClass::class));
}

/**
 * An engine that fails the test if a handler calls back into it, proving the handler declined
 * without resolving any sub-expression.
 */
function closureHandlerThrowingEngine(): ExpressionEngine
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
 * object identity, so a multi-return closure's branches can be pinned independently.
 */
final class ClosureHandlerArmStubEngine implements ExpressionEngine
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

        throw new RuntimeException('Unexpected expression passed to ClosureHandlerArmStubEngine');
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

/**
 * An engine that records whether the shadowed name was absent from $scope->localVarBindings at the
 * moment it was called, proving the handler suppressed it before descending into the body.
 */
final class ClosureHandlerScopeSpyEngine implements ExpressionEngine
{
    public bool $wasCalled = false;

    public bool $shadowedNameWasAbsent = false;

    /** @param array<string, mixed> $result */
    public function __construct(private AnalysisScope $scope, private string $shadowedName, private array $result) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        $this->wasCalled = true;
        $this->shadowedNameWasAbsent = ! array_key_exists($this->shadowedName, $this->scope->localVarBindings);

        return $this->result;
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

/**
 * An engine that always throws, for proving the finally block restores scope state even when the
 * body resolution itself blows up.
 */
function closureHandlerBlowingUpEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('body resolution exploded');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('body resolution exploded');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('body resolution exploded');
        }
    };
}

// ClosureHandler

it('resolves an arrow function\'s body through the engine callback, ignoring any return-type annotation', function () {
    $bodyExpr = new Variable('x');
    $expr = new ArrowFunction(['expr' => $bodyExpr, 'returnType' => new Identifier('int')]);
    $engine = new ClosureHandlerArmStubEngine([
        [$bodyExpr, ['type' => 'string', 'optional' => false]],
    ]);

    $result = (new ClosureHandler)->resolve($expr, closureHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('merges a multi-return closure body into a union via ValueResult::mergeUnion', function () {
    $returnA = new Variable('a');
    $returnB = new Variable('b');
    $expr = new ClosureExpr(['stmts' => [new Return_($returnA), new Return_($returnB)]]);
    $engine = new ClosureHandlerArmStubEngine([
        [$returnA, ['type' => 'string', 'optional' => false]],
        [$returnB, ['type' => 'number', 'optional' => false]],
    ]);

    $result = (new ClosureHandler)->resolve($expr, closureHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'string | number', 'optional' => false]);
});

it('falls back to the native return-type annotation when the body resolves to unknown', function () {
    $bodyExpr = new Variable('x');
    $expr = new ArrowFunction(['expr' => $bodyExpr, 'returnType' => new Identifier('int')]);
    $engine = new ClosureHandlerArmStubEngine([
        [$bodyExpr, ['type' => 'unknown', 'optional' => false]],
    ]);

    $result = (new ClosureHandler)->resolve($expr, closureHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('returns the unknown body result when there is no usable return-type annotation either', function () {
    $bodyExpr = new Variable('x');
    $expr = new ArrowFunction(['expr' => $bodyExpr]);
    $engine = new ClosureHandlerArmStubEngine([
        [$bodyExpr, ['type' => 'unknown', 'optional' => false]],
    ]);

    $result = (new ClosureHandler)->resolve($expr, closureHandlerTestScope(), $engine);

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
});

it('suppresses a closure param that shadows a populated localVarBindings entry, then restores it', function () {
    // This pins the ShadowedClosureParamResource invariant: a closure param that merely shares a
    // name with an outer local must not resolve through that outer binding just because nothing
    // else claimed it — see docs/components/resource-ast-analyzer.md, "Closure params vs.
    // AnalysisScope::$localVarBindings".
    $scope = closureHandlerTestScope();
    $outerBoundExpr = new Variable('outerSource');
    $scope->localVarBindings['slug'] = $outerBoundExpr;

    $bodyExpr = new Variable('slug');
    $param = new Param(new Variable('slug'));
    $expr = new ArrowFunction(['params' => [$param], 'expr' => $bodyExpr]);

    $engine = new ClosureHandlerScopeSpyEngine($scope, 'slug', ['type' => 'unknown', 'optional' => false]);

    $result = (new ClosureHandler)->resolve($expr, $scope, $engine);

    expect($engine->wasCalled)->toBeTrue()
        ->and($engine->shadowedNameWasAbsent)->toBeTrue()
        ->and($result)->toBe(['type' => 'unknown', 'optional' => false])
        ->and($scope->localVarBindings)->toBe(['slug' => $outerBoundExpr]);
});

it('restores the suppressed binding in a finally even when body resolution throws', function () {
    $scope = closureHandlerTestScope();
    $outerBoundExpr = new Variable('outerSource');
    $scope->localVarBindings['slug'] = $outerBoundExpr;

    $param = new Param(new Variable('slug'));
    $expr = new ArrowFunction(['params' => [$param], 'expr' => new Variable('slug')]);

    expect(fn () => (new ClosureHandler)->resolve($expr, $scope, closureHandlerBlowingUpEngine()))
        ->toThrow(RuntimeException::class);

    expect($scope->localVarBindings)->toBe(['slug' => $outerBoundExpr]);
});

it('declines a non-closure, non-arrow-function expression without calling the engine', function () {
    $expr = new MethodCall(new Variable('this'), 'foo');

    $result = (new ClosureHandler)->resolve($expr, closureHandlerTestScope(), closureHandlerThrowingEngine());

    expect($result)->toBeNull();
});
