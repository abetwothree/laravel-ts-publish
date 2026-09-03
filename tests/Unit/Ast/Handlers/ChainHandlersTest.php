<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MethodChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Role;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\HelperCallResource;
use Workbench\App\Http\Resources\MediaTypeResource;
use Workbench\App\Http\Resources\RelationChainResource;
use Workbench\App\Http\Resources\UnitEnumResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Kpi;
use Workbench\App\Models\Order;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function chainHandlersThrowingEngine(): ExpressionEngine
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
 * Resolves exactly the map closure to a canned body result, recording the scope bindings the chain
 * handler had installed at the moment it recursed — the only way to see them before the restore.
 */
final class ChainHandlersMapStubEngine implements ExpressionEngine
{
    public ?string $boundRelationModel = null;

    public ?string $boundParamModel = null;

    /** @param array<string, mixed> $bodyResult */
    public function __construct(
        private Expr $mapArg,
        private array $bodyResult,
        private AnalysisScope $scope,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(Expr $expr): array
    {
        if ($expr !== $this->mapArg) {
            throw new RuntimeException('Unexpected expression passed to ChainHandlersMapStubEngine');
        }

        $this->boundRelationModel = $this->scope->closureRelationModelClass;
        $this->boundParamModel = $this->scope->varModelBindings['member'] ?? null;

        return $this->bodyResult;
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

/** `$this->{$prop}` */
function chainThisProp(string $prop): PropertyFetch
{
    return new PropertyFetch(new Variable('this'), $prop);
}

it('resolves $this->user?->name to the related column type, made nullable by ?->', function () {
    $expr = new NullsafePropertyFetch(chainThisProp('user'), 'name');
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new PropertyChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBe(['type' => 'string | null', 'optional' => false]);
});

it('carries the enum FQCN out of a nullsafe chain ending on an enum-cast column', function () {
    $expr = new NullsafePropertyFetch(chainThisProp('user'), 'role');
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new PropertyChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBe([
        'type' => 'RoleType | null',
        'optional' => false,
        'directEnumFqcn' => Role::class,
    ]);
});

it('resolves a 3-deep chain through the $this->resource wrapper step', function () {
    $expr = new PropertyFetch(new PropertyFetch(chainThisProp('resource'), 'user'), 'name');
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new PropertyChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBe(['type' => 'string', 'optional' => false]);
});

it('declines a property fetch rooted at a plain variable, not $this', function () {
    $expr = new PropertyFetch(new Variable('other'), 'name');
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new PropertyChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBeNull();
});

it('resolves $this->resource->name / ->value on an enum-wrapped resource', function () {
    $scope = new AnalysisScope(new ReflectionClass(MediaTypeResource::class));
    $handler = new PropertyChainHandler;

    $name = $handler->resolve(new PropertyFetch(chainThisProp('resource'), 'name'), $scope, chainHandlersThrowingEngine());
    $value = $handler->resolve(new PropertyFetch(chainThisProp('resource'), 'value'), $scope, chainHandlersThrowingEngine());

    expect($name)->toBe(['type' => 'string', 'optional' => false])
        ->and($value)->toBe(['type' => 'string', 'optional' => false]);
});

// Guard-order pin: the wrapped-ENUM branch must run before the wrapped-MODEL branch, and BOTH must
// really claim `value` or this pins nothing. UnitEnumResource wraps an unbacked enum ('string | number')
// over a Kpi scope whose `value` column is an integer ('number') — swapping the arms returns 'number'.
it('tries the wrapped-enum branch before the wrapped-model branch', function () {
    $scope = new AnalysisScope(new ReflectionClass(UnitEnumResource::class), Kpi::class);

    $result = (new PropertyChainHandler)->resolve(
        new PropertyFetch(chainThisProp('resource'), 'value'),
        $scope,
        chainHandlersThrowingEngine(),
    );

    expect(resolve(ModelAttributeResolver::class)->resolveAttribute(Kpi::class, 'value')['type'])->toBe('number')
        ->and($result)->toBe(['type' => 'string | number', 'optional' => false]);
});

it('declines a plain method call it does not claim', function () {
    $expr = new MethodCall(chainThisProp('user'), 'fullName');
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new MethodChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBeNull();
});

it('degrades a nullsafe method chain with no resolvable return type to unknown', function () {
    $expr = new NullsafeMethodCall(chainThisProp('user'), 'notAMethodAnywhere');
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

    $result = (new MethodChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBe(['type' => 'unknown', 'optional' => false]);
});

it('resolves a relation collection chain to the element-typed array', function () {
    $expr = new MethodCall(chainThisProp('members'), 'take', [new Arg(new Int_(5))]);
    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);

    $result = (new RelationCollectionChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBe(['type' => 'User[]', 'optional' => false, 'modelFqcn' => User::class]);
});

// Guard-order pin: `$this->members->take(5)` matches BOTH the collection-chain guard and the
// `$this->anyProp->method()` guard below it, and the second returns unconditionally — so swapping
// the two turns this back into unknown. Same 1-deep overlap the `count()` case below relies on.
it('tries the collection chain before the wrapped-method branch', function () {
    $expr = new MethodCall(chainThisProp('members'), 'take', [new Arg(new Int_(5))]);
    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);

    $result = (new RelationCollectionChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result['type'])->toBe('User[]');
});

// The one branch of the chain analysis that recurses through the engine: `map()`'s closure body.
it('resolves a take()->map()->values() chain through the engine, array-wrapping the body', function () {
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('member'))],
        'expr' => new Variable('mapBody'),
    ]);

    $chain = new MethodCall(
        new MethodCall(
            new MethodCall(chainThisProp('members'), 'take', [new Arg(new Int_(5))]),
            'map',
            [new Arg($closure)],
        ),
        'values',
    );

    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);

    $engine = new ChainHandlersMapStubEngine($closure, ['type' => '{ id: number }', 'optional' => false], $scope);

    $result = (new RelationCollectionChainHandler)->resolve($chain, $scope, $engine);

    expect($result)->toBe(['type' => '{ id: number }[]', 'optional' => false])
        ->and($engine->boundRelationModel)->toBe(User::class)
        ->and($engine->boundParamModel)->toBe(User::class)
        ->and($scope->closureRelationModelClass)->toBeNull()
        ->and($scope->varModelBindings)->toBe([]);
});

it('declines a method call rooted at a bare variable, not $this', function () {
    $expr = new MethodCall(new Variable('members'), 'take', [new Arg(new Int_(5))]);
    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);

    $result = (new RelationCollectionChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBeNull();
});

// The chain analysis returns null for a 1-deep `count()`, so the wrapped-method branch runs and its
// knownMethodRule() gives number. Asserted through the full engine, not the handler alone.
it('lets $this->items->count() fall through the chain analysis to the known-method rule', function () {
    $expr = new MethodCall(chainThisProp('items'), 'count');
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(HelperCallResource::class), Order::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'number', 'optional' => false]);
});

it('resolves a generic $this->method() through the subject method resolver', function () {
    $expr = new MethodCall(new Variable('this'), 'sizeUnit');
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(MediaTypeResource::class));

    expect($analyzer->resolve($expr))->toBe(['type' => 'string', 'optional' => false]);
});

// Named collection arguments. pluck(key:, value:) is the one that genuinely reorders: written this way,
// index 0 is the key column, and the old read typed the plucked list from it.

it('reads pluck(key: …, value: …) by name inside a relation chain', function () {
    $expr = new MethodCall(chainThisProp('members'), 'pluck', [
        new Arg(new String_('id'), name: new Identifier('key')),
        new Arg(new String_('email'), name: new Identifier('value')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);

    $result = (new RelationCollectionChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result['type'])->toBe('string[] | Record<string, string>');
});

it('reads take(limit: …) and map(callback: …) by name', function () {
    $closure = new ArrowFunction([
        'params' => [new Param(new Variable('member'))],
        'expr' => new Variable('mapBody'),
    ]);
    $chain = new MethodCall(
        new MethodCall(chainThisProp('members'), 'take', [new Arg(new Int_(5), name: new Identifier('limit'))]),
        'map',
        [new Arg($closure, name: new Identifier('callback'))],
    );
    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);
    $engine = new ChainHandlersMapStubEngine($closure, ['type' => '{ id: number }', 'optional' => false], $scope);

    $result = (new RelationCollectionChainHandler)->resolve($chain, $scope, $engine);

    expect($result)->toBe(['type' => '{ id: number }[]', 'optional' => false]);
});

it('treats first(default: …) as non-terminal, the same as a positional default', function () {
    $expr = new MethodCall(
        new MethodCall(chainThisProp('members'), 'take', [new Arg(new Int_(5))]),
        'first',
        [new Arg(new ConstFetch(new Name('null')), name: new Identifier('default'))],
    );
    $scope = new AnalysisScope(new ReflectionClass(RelationChainResource::class), Team::class);

    $result = (new RelationCollectionChainHandler)->resolve($expr, $scope, chainHandlersThrowingEngine());

    expect($result)->toBeNull();
});
