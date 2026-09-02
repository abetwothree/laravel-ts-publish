<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\FluentSelfResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Post;

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function staticCallHandlerThrowingEngine(): ExpressionEngine
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
 * A stub engine resolving each distinct expression instance to its own canned result, keyed by
 * object identity — mirrors ClosureHandlerArmStubEngine's convention for the same reason.
 */
final class StaticCallHandlerArmStubEngine implements ExpressionEngine
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

        throw new RuntimeException('Unexpected expression passed to StaticCallHandlerArmStubEngine');
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

// ToResourceHandler

it('declines a method call named neither toResource nor toResourceCollection', function () {
    $expr = new MethodCall(new Variable('this'), 'somethingElse');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new ToResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBeNull();
});

it('reads toResource(resourceClass: …) and toResourceCollection(resourceClass: …) by name', function () {
    $explicit = fn (): Arg => new Arg(new ClassConstFetch(new Name(PostResource::class), 'class'), name: new Identifier('resourceClass'));
    $single = new MethodCall(new Variable('model'), 'toResource', [$explicit()]);
    $many = new MethodCall(new Variable('models'), 'toResourceCollection', [$explicit()]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    expect((new ToResourceHandler)->resolve($single, $scope, staticCallHandlerThrowingEngine()))
        ->toBe(['type' => 'PostResource', 'optional' => false, 'resourceFqcn' => PostResource::class])
        ->and((new ToResourceHandler)->resolve($many, $scope, staticCallHandlerThrowingEngine()))
        ->toBe(['type' => 'PostResource[]', 'optional' => false, 'resourceFqcn' => PostResource::class]);
});

// StaticCallHandler

it('resolves PostResource::collection($this->posts) to the resource channel with a [] type', function () {
    $expr = new StaticCall(new Name(PostResource::class), 'collection', [
        new Arg(new PropertyFetch(new Variable('this'), 'posts')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'PostResource[]',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
    ]);
});

it('resolves EnumResource::make($this->status) to the enum channel', function () {
    $expr = new StaticCall(new Name(EnumResource::class), 'make', [
        new Arg(new PropertyFetch(new Variable('this'), 'status')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'StatusType',
        'optional' => false,
        'enumFqcn' => Status::class,
    ]);
});

it('routes $this->resource::m() to analyzeStaticMethodOnResource() even inside a closure with a related model bound — guard 5 must precede guard 6', function () {
    // $this->resource::tableName() — guard 5 (`$this->resource::staticMethod()`) and guard 6
    // (closure-context `$this->relation::staticMethod()`) share the same PropertyFetch-receiver
    // shape; only guard 5's extra `name->toString() === 'resource'` check tells them apart. Setting
    // closureRelationModelClass to a model with no tableName() method makes a guard-6 misroute
    // observable: it would degrade to unknown instead of reflecting Post::tableName()'s `string`.
    $expr = new StaticCall(
        new PropertyFetch(new Variable('this'), 'resource'),
        'tableName',
    );
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);
    $scope->closureRelationModelClass = Address::class;

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'string',
        'optional' => false,
    ]);
});

it('keeps the foreign-receiver boundary: a non-self-returning method on a foreign resource class declines', function () {
    // new CategoryResource($this->parent)->summary() — CategoryResource::summary() declares `: array`,
    // not a self-returning type, and CategoryResource is not the subject under analysis
    // (FluentSelfResource), so this must decline rather than resolve against the wrong resource's
    // same-named method. See FluentSelfResource::foreign_summary in the workbench.
    $receiver = new New_(new Name(CategoryResource::class), [
        new Arg(new PropertyFetch(new Variable('this'), 'parent')),
    ]);
    $expr = new MethodCall($receiver, 'summary');
    $scope = new AnalysisScope(new ReflectionClass(FluentSelfResource::class));

    $engine = new StaticCallHandlerArmStubEngine([
        [$receiver, ['type' => 'CategoryResource', 'optional' => false, 'resourceFqcn' => CategoryResource::class]],
    ]);

    $result = (new StaticCallHandler)->resolve($expr, $scope, $engine);

    expect($result)->toBeNull();
});

it('declines an expression it does not claim', function () {
    $expr = new MethodCall(new Variable('this'), 'somethingElse');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// NewResourceHandler

it('resolves new PostResource(...) to the resource channel', function () {
    $expr = new New_(new Name(PostResource::class), [
        new Arg(new PropertyFetch(new Variable('this'), 'post')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new NewResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'PostResource',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
    ]);
});

it('declines a node outside its claimed New_ class', function () {
    // NewResourceHandler only claims New_::class, and analyzeNewResource() always resolves to a
    // concrete result (down to the `unknown` floor) for any New_ node it is actually given — so the
    // only reachable decline is the top-level class guard itself, exercised here directly.
    $expr = new String_('not a new expression');
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new NewResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBeNull();
});

// Resource payloads written by name — `resource:` is JsonResource::__construct()'s and ::collection()'s
// parameter, and make() forwards it there through `new static(...$parameters)`.

it('resolves PostResource::collection(resource: $this->whenLoaded(…)) as optional through the named payload', function () {
    $expr = new StaticCall(new Name(PostResource::class), 'collection', [
        new Arg(new MethodCall(new Variable('this'), 'whenLoaded', [new Arg(new String_('posts'))]), name: new Identifier('resource')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'PostResource[]',
        'optional' => true,
        'resourceFqcn' => PostResource::class,
    ]);
});

it('resolves EnumResource::make(resource: $this->status) to the enum channel', function () {
    $expr = new StaticCall(new Name(EnumResource::class), 'make', [
        new Arg(new PropertyFetch(new Variable('this'), 'status'), name: new Identifier('resource')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class), Post::class);

    $result = (new StaticCallHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'StatusType',
        'optional' => false,
        'enumFqcn' => Status::class,
    ]);
});

it('resolves new PostResource(resource: $this->when(…)) as optional through the named payload', function () {
    $expr = new New_(new Name(PostResource::class), [
        new Arg(new MethodCall(new Variable('this'), 'when', [new Arg(new Variable('flag')), new Arg(new Variable('post'))]), name: new Identifier('resource')),
    ]);
    $scope = new AnalysisScope(new ReflectionClass(PostResource::class));

    $result = (new NewResourceHandler)->resolve($expr, $scope, staticCallHandlerThrowingEngine());

    expect($result)->toBe([
        'type' => 'PostResource',
        'optional' => true,
        'resourceFqcn' => PostResource::class,
    ]);
});
