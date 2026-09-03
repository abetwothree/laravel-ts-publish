<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ArrayMergeHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\BinaryOpHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CastHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClassConstantHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClosureHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CoalesceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConstFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MethodChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\TernaryHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\VariableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;
use Workbench\Crm\Models\User as CrmUser;

/**
 * The documented dispatch order for the resource profile. A change here without a matching change
 * in ResourceExpressionHandlers::handlers() is exactly the defect this test exists to catch.
 *
 * @return list<class-string<ExpressionHandler>>
 */
function resourceExpressionHandlerOrder(): array
{
    return [
        FirstClassCallableHandler::class,
        CastHandler::class,
        ScalarHandler::class,
        ConstFetchHandler::class,
        ClassConstantHandler::class,
        BinaryOpHandler::class,
        CoalesceHandler::class,
        ArrayMergeHandler::class,
        KnownFunctionCallHandler::class,
        ClosureHandler::class,
        ConditionalMethodHandler::class,
        ToResourceHandler::class,
        InertiaWrapperHandler::class,
        StaticCallHandler::class,
        NewResourceHandler::class,
        ThisPropertyHandler::class,
        RelationFilterHandler::class,
        InlineArrayHandler::class,
        MethodChainHandler::class,
        PropertyChainHandler::class,
        RelationCollectionChainHandler::class,
        VariableHandler::class,
        TernaryHandler::class,
        KnownMethodRuleHandler::class,
    ];
}

/** A throwaway engine: make()/generic() only construct handlers, they never resolve anything. */
function resourceExpressionHandlersTestEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called while merely constructing handlers');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called while merely constructing handlers');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called while merely constructing handlers');
        }
    };
}

it('returns all 24 handlers in the documented dispatch order', function () {
    $classes = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::make(resourceExpressionHandlersTestEngine()),
    );

    expect($classes)->toBe(resourceExpressionHandlerOrder());
});

it('excludes exactly the three resource-only handlers from generic(), same relative order', function () {
    $classes = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::generic(),
    );

    $expected = array_values(array_filter(
        resourceExpressionHandlerOrder(),
        fn (string $class): bool => ! in_array($class, [
            ConditionalMethodHandler::class,
            ToResourceHandler::class,
            RelationFilterHandler::class,
        ], true),
    ));

    expect($classes)->toHaveCount(21)
        ->and($classes)->toBe($expected);
});

// Ordering pin #1: both handlers claim a first-class-callable $this->when(...) —
// isThisMethodCall() matches on method name alone, ignoring args — so if ConditionalMethodHandler
// ran first it would call getArgs(), which asserts !isFirstClassCallable() and fatals.
it('tries FirstClassCallableHandler before ConditionalMethodHandler for a first-class-callable $this->when(...)', function () {
    $expr = new MethodCall(new Variable('this'), 'when', [new VariadicPlaceholder]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'unknown', 'optional' => false]);
});

// Ordering pin #2: both handlers claim NullsafeMethodCall, but MethodChainHandler's floor is
// ValueResult::unknown(), never null, so it would win every NullsafeMethodCall if it ran first —
// degrading this Pick<> reference to a plain reflected type.
it('tries RelationFilterHandler before MethodChainHandler for $this->relation?->only([...])', function () {
    $expr = new NullsafeMethodCall(
        new PropertyFetch(new Variable('this'), 'post'),
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('id')),
            new ArrayItem(new String_('title')),
        ]))],
    );
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe([
        'type' => "Pick<Post, 'id' | 'title'> | null",
        'optional' => false,
        'modelFqcn' => Post::class,
    ]);
});

// Ordering pin #3: both handlers claim $this->{multi-FQCN accessor}, but PropertyChainHandler's
// last-step branch has no embeddedModelFqcns equivalent — if it ran first, the alias rewriter
// would lose the CrmUser arm entirely and both union members would render as bare 'User'.
it('tries ThisPropertyHandler before PropertyChainHandler for a multi-FQCN accessor', function () {
    $expr = new PropertyFetch(new Variable('this'), 'last_user_activity_by');
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(WarehouseResource::class), Warehouse::class);

    expect($analyzer->resolve($expr))->toBe([
        'type' => 'User | User | null',
        'optional' => false,
        'embeddedModelFqcns' => [CrmUser::class, User::class],
    ]);
});

// Ordering pin #4: StaticCallHandler's last arm claims every StaticCall and never declines, so if it
// ran first it would reflect `Inertia::always` as an ordinary static method and floor this at
// unknown instead of resolving the wrapped value.
it('tries InertiaWrapperHandler before StaticCallHandler for Inertia::always(...)', function () {
    $expr = new StaticCall(new Name('Inertia'), 'always', [new Arg(new String_('en'))]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'string', 'optional' => false]);
});

// Ordering pin #5: KnownFunctionCallHandler gates only the inner auth() call on isFirstClassCallable(),
// never the outer MethodCall, so if it ran first it would answer a first-class-callable auth()->user(...)
// with the guard's model — a confident type for a Closure.
it('tries FirstClassCallableHandler before KnownFunctionCallHandler for auth()->user(...)', function () {
    $expr = new MethodCall(new FuncCall(new Name('auth')), 'user', [new VariadicPlaceholder]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'unknown', 'optional' => false]);
});

// Ordering pin #6: ToResourceHandler matches on the method name alone and then calls getArgs(),
// which asserts !isFirstClassCallable() — so without FirstClassCallableHandler ahead of it a
// first-class-callable $this->post->toResource(...) fatals with an AssertionError.
it('tries FirstClassCallableHandler before ToResourceHandler for $this->post->toResource(...)', function () {
    $expr = new MethodCall(new PropertyFetch(new Variable('this'), 'post'), 'toResource', [new VariadicPlaceholder]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'unknown', 'optional' => false]);
});
