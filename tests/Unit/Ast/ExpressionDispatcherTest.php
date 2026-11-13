<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;
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

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('dispatch() must not call back into the engine in these tests');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
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

// What a handler that keeps no held object reads, as an array's item, is no longer the value a closure returns.
it('reads a handler that keeps no held object away from a closure\'s returned value, and puts it back', function () {
    $scope = dispatcherTestScope();
    $scope->readsCallableReturn = true;
    $seen = [];
    $record = function (string $name) use ($scope, &$seen): array {
        $seen[$name] = $scope->readsCallableReturn;

        return ['type' => 'string', 'optional' => false];
    };
    $plain = new class($record) implements ExpressionHandler
    {
        public function __construct(private Closure $record) {}

        public function nodeClasses(): array
        {
            return [String_::class];
        }

        public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
        {
            return ($this->record)('plain');
        }
    };
    $keeping = new class($record) implements ExpressionHandler, KeepsHeldObjects
    {
        public function __construct(private Closure $record) {}

        public function nodeClasses(): array
        {
            return [Int_::class];
        }

        public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
        {
            return ($this->record)('keeping');
        }
    };
    $dispatcher = new ExpressionDispatcher([$plain, $keeping]);

    $dispatcher->dispatch(new String_('x'), $scope, dispatcherTestEngine());
    $dispatcher->dispatch(new Int_(1), $scope, dispatcherTestEngine());

    expect($seen)->toBe(['plain' => false, 'keeping' => true])
        ->and($scope->readsCallableReturn)->toBeTrue();
});

// A handler that reads a gap and returns without it consumed the gap, so no merge fills it and its warning rises.
it('raises the warnings of a gap a handler\'s reads made that its result, or a decline, no longer spells', function (?string $spelled, bool $raises) {
    $scope = dispatcherTestScope();
    $reads = function () use ($scope, $spelled): ?array {
        $arm = new Variable('arm', ['startLine' => 7]);
        $id = DroppedUnionArms::gap($scope, 'match-arm', $arm, 'null');
        DroppedUnionArms::hold($id, 'held', $arm, stdClass::class);

        return $spelled === null ? null : ['type' => str_replace('$gap', VisibleGaps::arm($id), $spelled), 'optional' => false];
    };
    $handler = new class($reads) implements ExpressionHandler
    {
        public function __construct(private Closure $reads) {}

        public function nodeClasses(): array
        {
            return [String_::class];
        }

        public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
        {
            return ($this->reads)();
        }
    };

    new ExpressionDispatcher([$handler])->dispatch(new String_('x'), $scope, dispatcherTestEngine());

    expect(array_column(AnalysisWarnings::all(), 'message'))->toBe($raises ? ['held'] : []);
})->with([
    'a result spelling the gap' => ['null | $gap', false],
    'a result without it' => ['number', true],
    'a decline' => [null, true],
]);

// A gap a value carries in, as a bound argument does, was made before the read: an inner result spelling it brings it.
it('raises the warnings of a gap an inner dispatch\'s result spelled that the outer result drops, unless a merge inside filled it', function (string $outer, bool $fills, bool $raises) {
    $scope = dispatcherTestScope();
    $arm = new Variable('arm', ['startLine' => 7]);
    $id = DroppedUnionArms::gap($scope, 'match-arm', $arm, 'null');
    DroppedUnionArms::hold($id, 'held', $arm, stdClass::class);
    $dispatcher = null;
    $bound = new class(['type' => 'null | '.VisibleGaps::arm($id), 'optional' => false]) implements ExpressionHandler
    {
        public function __construct(private array $bound) {}

        public function nodeClasses(): array
        {
            return [Variable::class];
        }

        public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
        {
            return $this->bound;
        }
    };
    $reads = function (AnalysisScope $scope, ExpressionEngine $engine) use (&$dispatcher, $outer, $fills, $id): array {
        $dispatcher->dispatch(new Variable('x'), $scope, $engine);

        if ($fills) {
            DroppedUnionArms::filled([$id]);
        }

        return ['type' => str_replace('$gap', VisibleGaps::arm($id), $outer), 'optional' => false];
    };
    $reader = new class($reads) implements ExpressionHandler
    {
        public function __construct(private Closure $reads) {}

        public function nodeClasses(): array
        {
            return [String_::class];
        }

        public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
        {
            return ($this->reads)($scope, $engine);
        }
    };

    $dispatcher = new ExpressionDispatcher([$bound, $reader]);
    $dispatcher->dispatch(new String_('x'), $scope, dispatcherTestEngine());

    expect(array_column(AnalysisWarnings::all(), 'message'))->toBe($raises ? ['held'] : []);
})->with([
    'an outer result spelling the gap' => ['null | $gap', false, false],
    'an outer result without it' => ['number', false, true],
    'a merge inside the outer read filling it' => ['number', true, false],
]);

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
