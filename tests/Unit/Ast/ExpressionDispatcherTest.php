<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * A throwaway scope for tests that never inspect its contents.
 */
function dispatcherTestScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(stdClass::class));
}

/**
 * A throwaway engine for tests that never expect the dispatcher to call back into it.
 */
function dispatcherTestEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('dispatch() must not call back into the engine in these tests');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('dispatch() must not call back into the engine in these tests');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('dispatch() must not call back into the engine in these tests');
        }
    };
}

it('returns the first non-null result, proving a declining handler falls through to the next', function () {
    $declining = new FakeExpressionHandler([String_::class], null);
    $resolving = new FakeExpressionHandler([String_::class], ['type' => 'string', 'optional' => false]);

    $dispatcher = new ExpressionDispatcher([$declining, $resolving]);

    $result = $dispatcher->dispatch(new String_('x'), dispatcherTestScope(), dispatcherTestEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => false])
        ->and($declining->resolveCallCount)->toBe(1)
        ->and($resolving->resolveCallCount)->toBe(1);
});

it('returns null when no registered handler claims the node class', function () {
    $handler = new FakeExpressionHandler([String_::class], ['type' => 'string', 'optional' => false]);

    $dispatcher = new ExpressionDispatcher([$handler]);

    $result = $dispatcher->dispatch(new Int_(1), dispatcherTestScope(), dispatcherTestEngine());

    expect($result)->toBeNull()
        ->and($handler->resolveCallCount)->toBe(0);
});

it('matches a concrete node against a handler claiming an abstract base class', function () {
    $handler = new FakeExpressionHandler([Scalar::class], ['type' => 'string', 'optional' => false]);

    $dispatcher = new ExpressionDispatcher([$handler]);

    $result = $dispatcher->dispatch(new String_('x'), dispatcherTestScope(), dispatcherTestEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('memoizes the candidate list per concrete node class, calling nodeClasses() only once', function () {
    $handler = new FakeExpressionHandler([String_::class], ['type' => 'string', 'optional' => false]);
    $dispatcher = new ExpressionDispatcher([$handler]);

    $dispatcher->dispatch(new String_('a'), dispatcherTestScope(), dispatcherTestEngine());
    $dispatcher->dispatch(new String_('b'), dispatcherTestScope(), dispatcherTestEngine());
    $dispatcher->dispatch(new String_('c'), dispatcherTestScope(), dispatcherTestEngine());

    expect($handler->nodeClassesCallCount)->toBe(1)
        ->and($handler->resolveCallCount)->toBe(3);
});

it('caches an empty candidate list too, never re-scanning a node class no handler claims', function () {
    $handler = new FakeExpressionHandler([String_::class], ['type' => 'string', 'optional' => false]);
    $dispatcher = new ExpressionDispatcher([$handler]);

    $dispatcher->dispatch(new Int_(1), dispatcherTestScope(), dispatcherTestEngine());
    $dispatcher->dispatch(new Int_(2), dispatcherTestScope(), dispatcherTestEngine());
    $dispatcher->dispatch(new Int_(3), dispatcherTestScope(), dispatcherTestEngine());

    expect($handler->nodeClassesCallCount)->toBe(1)
        ->and($handler->resolveCallCount)->toBe(0);
});

/**
 * A configurable spy handler: claims a fixed node-class list and returns a canned result (or
 * declines with null), counting calls to both methods so tests can prove dispatch order and memoization.
 */
final class FakeExpressionHandler implements ExpressionHandler
{
    public int $nodeClassesCallCount = 0;

    public int $resolveCallCount = 0;

    /**
     * @param  list<class-string<Expr>>  $claims
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(private array $claims, private ?array $result) {}

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        $this->nodeClassesCallCount++;

        return $this->claims;
    }

    /** @return array<string, mixed>|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $this->resolveCallCount++;

        return $this->result;
    }
}
