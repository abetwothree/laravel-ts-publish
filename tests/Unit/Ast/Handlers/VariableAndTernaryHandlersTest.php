<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\TernaryHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\VariableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\HelperCallResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

/**
 * An engine that fails the test if a handler calls back into it.
 */
function variableHandlersThrowingEngine(): ExpressionEngine
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

        public function returnArrayAnalysis(Array_ $array): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}

/**
 * A minimal recursive engine over a fixed handler list — the re-entrancy guard is only observable
 * when a handler's callback really re-enters the same handler.
 */
final class VariableHandlersLoopEngine implements ExpressionEngine
{
    private ExpressionDispatcher $dispatcher;

    /** @param list<ExpressionHandler> $handlers */
    public function __construct(array $handlers, private AnalysisScope $scope)
    {
        $this->dispatcher = new ExpressionDispatcher($handlers);
    }

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        return $this->dispatcher->dispatch($expr, $this->scope, $this) ?? ValueResult::unknown();
    }

    public function spreadAnalysis(string $methodName): ?MethodAnalysis
    {
        throw new RuntimeException('spreadAnalysis() must not be called in this case');
    }

    public function returnArrayAnalysis(Array_ $array): MethodAnalysis
    {
        throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
    }
}

it('resolves a bound variable property fetch to the related model column type', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['item'] = User::class;

    $result = (new VariableHandler)->resolve(
        new PropertyFetch(new Variable('item'), 'name'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('resolves a bare bound variable to its model type', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['author'] = User::class;

    $result = (new VariableHandler)->resolve(
        new Variable('author'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
});

it('resolves a bare variable bound to a whole relation collection to the collection type', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varCollectionBindings['authors'] = ['type' => 'User[]', 'modelFqcn' => User::class];

    $result = (new VariableHandler)->resolve(
        new Variable('authors'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class]);
});

it('resolves $variable->pluck() against the ambient closure relation model', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->closureRelationModelClass = User::class;

    $result = (new VariableHandler)->resolve(
        new MethodCall(new Variable('users'), 'pluck', [new Arg(new String_('name'))]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'string[]', 'optional' => false]);
});

// Guard-order pin: the pluck guard must precede the generic `$variable->method()` guard. Both claim
// this node — the generic guard's bound model is non-null here — but it resolves `pluck` as a method
// on User, which has none, so swapping the two turns this into 'unknown'.
it('tries the pluck guard before the generic bound-method guard', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->closureRelationModelClass = User::class;
    $scope->varModelBindings['users'] = User::class;

    $result = (new VariableHandler)->resolve(
        new MethodCall(new Variable('users'), 'pluck', [new Arg(new String_('name'))]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect(method_exists(User::class, 'pluck'))->toBeFalse()
        ->and($result)->toBe(['type' => 'string[]', 'optional' => false]);
});

// Guard-order pin: the model-binding guard must precede the expression-binding guard. Both claim
// this node — `$author` is in both maps — and the expression binding resolves to a string literal,
// so swapping the two returns 'string' instead of the model type.
it('tries the model binding before the local-expression binding for a bare variable', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->varModelBindings['author'] = User::class;
    $scope->localVarBindings['author'] = new String_('literal');

    $engine = new VariableHandlersLoopEngine([new ScalarHandler, new VariableHandler], $scope);

    expect($engine->resolve(new String_('literal')))->toBe(['type' => 'string', 'optional' => false])
        ->and($engine->resolve(new Variable('author')))
        ->toBe(['type' => 'User', 'optional' => false, 'modelFqcn' => User::class]);
});

it('degrades a cyclic local-variable binding to unknown instead of recursing forever', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->localVarBindings['a'] = new Variable('b');
    $scope->localVarBindings['b'] = new Variable('a');

    $engine = new VariableHandlersLoopEngine([new VariableHandler], $scope);

    expect($engine->resolve(new Variable('a')))->toBe(['type' => 'unknown', 'optional' => false])
        ->and($scope->resolvingLocalVars)->toBe([]);
});

it('declines an expression it does not claim, leaving later handlers their turn', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $handler = new VariableHandler;
    $engine = variableHandlersThrowingEngine();

    expect($handler->resolve(new Variable('unbound'), $scope, $engine))->toBeNull()
        ->and($handler->resolve(new PropertyFetch(new Variable('unbound'), 'name'), $scope, $engine))->toBeNull()
        ->and($handler->resolve(new MethodCall(new Variable('unbound'), 'thing'), $scope, $engine))->toBeNull();
});

it('unions both ternary arms', function () {
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    $expr = new Ternary(new Variable('flag'), new String_('yes'), new Int_(1));

    expect($analyzer->resolve($expr))->toBe(['type' => 'string | number', 'optional' => false]);
});

it('unions an elvis expression through its condition as the truthy arm', function () {
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    $expr = new Ternary(new String_('yes'), null, new Int_(1));

    expect($analyzer->resolve($expr))->toBe(['type' => 'string | number', 'optional' => false]);
});

it('declines anything that is not a ternary', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new TernaryHandler)->resolve(new Variable('x'), $scope, variableHandlersThrowingEngine());

    expect($result)->toBeNull();
});

it('applies the known-method floor to a count() on an unclaimed receiver', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperCallResource::class), Order::class);

    $result = (new KnownMethodRuleHandler)->resolve(
        new MethodCall(new PropertyFetch(new Variable('this'), 'items'), 'count'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'number', 'optional' => false]);
});

it('applies the known-method floor to can() on a method-call receiver', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperCallResource::class), Order::class);

    $result = (new KnownMethodRuleHandler)->resolve(
        new MethodCall(new MethodCall(new Variable('request'), 'user'), 'can', [new Arg(new String_('view'))]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'boolean', 'optional' => false]);
});

it('declines a method call no known-method rule matches', function () {
    $scope = new AnalysisScope(new ReflectionClass(HelperCallResource::class), Order::class);

    $result = (new KnownMethodRuleHandler)->resolve(
        new MethodCall(new Variable('thing'), 'somethingUnknown'),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBeNull();
});

it('reads $variable->pluck(key: …, value: …) by name', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $scope->closureRelationModelClass = User::class;

    $result = (new VariableHandler)->resolve(
        new MethodCall(new Variable('users'), 'pluck', [
            new Arg(new String_('id'), name: new Identifier('key')),
            new Arg(new String_('name'), name: new Identifier('value')),
        ]),
        $scope,
        variableHandlersThrowingEngine(),
    );

    expect($result)->toBe(['type' => 'string[]', 'optional' => false]);
});

it('reads $variable->map(callback: …) by name', function () {
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $body = new PropertyFetch(new Variable('user'), 'name');
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('user'), type: new Name(User::class))],
        'expr' => $body,
    ]);
    $expr = new MethodCall(new Variable('users'), 'map', [new Arg($closure, name: new Identifier('callback'))]);
    $engine = new VariableHandlersLoopEngine([new VariableHandler], $scope);

    expect((new VariableHandler)->resolve($expr, $scope, $engine))->toBe(['type' => 'string[]', 'optional' => false]);
});
