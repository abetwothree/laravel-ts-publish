<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ArrayMergeHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\BinaryOpHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CastHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClassConstantHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ClosureHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CoalesceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CollectionPipelineHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConstFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaWrapperHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\MatchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\OffsetReadHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ScalarHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\TernaryHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\VariableHandler;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
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
use Workbench\App\Events\PayloadDiffersEvent;
use Workbench\App\Http\Middleware\HandleInertiaRequests;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\ReceiverMethodResource;
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
        PropertyChainHandler::class,
        RelationCollectionChainHandler::class,
        CollectionPipelineHandler::class,
        VariableHandler::class,
        TernaryHandler::class,
        MatchHandler::class,
        OffsetReadHandler::class,
        ReceiverPropertyFetchHandler::class,
        ReceiverMethodCallHandler::class,
        KnownMethodRuleHandler::class,
    ];
}

/** A throwaway engine: make() and forSubject() only construct handlers, they never resolve anything. */
function resourceExpressionHandlersTestEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called while merely constructing handlers');
        }

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called while merely constructing handlers');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called while merely constructing handlers');
        }
    };
}

it('returns all 28 handlers in the documented dispatch order', function () {
    $classes = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::make(resourceExpressionHandlersTestEngine()),
    );

    expect($classes)->toBe(resourceExpressionHandlerOrder());
});

// A model's getter body reads the model's own relations, whose only()/except() only RelationFilterHandler types.
it('keeps RelationFilterHandler in forModelClosures() and excludes ConditionalMethodHandler and ToResourceHandler, same relative order', function () {
    $classes = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::forModelClosures(),
    );

    $expected = array_values(array_filter(
        resourceExpressionHandlerOrder(),
        fn (string $class): bool => ! in_array($class, [ConditionalMethodHandler::class, ToResourceHandler::class], true),
    ));

    expect($classes)->toHaveCount(26)
        ->and($classes)->toBe($expected)
        ->and($classes)->toContain(RelationFilterHandler::class);
});

// Only JsonResource::resolve() drops the MissingValue the when*() family returns. toResource() and a relation filter
// mean the same in any body, and a model's own method needs RelationFilterHandler for a to-many or map-proxy filter.
it('excludes only ConditionalMethodHandler from forNonResourceSubjects(), same relative order', function () {
    $classes = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::forNonResourceSubjects(),
    );

    expect($classes)->toHaveCount(27)
        ->and($classes)->toBe(array_values(array_diff(resourceExpressionHandlerOrder(), [ConditionalMethodHandler::class])));
});

it("gives a JsonResource subject make() and any other subject forNonResourceSubjects()'s handler classes", function (string $subject, bool $isResource) {
    $classes = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::forSubject($subject, resourceExpressionHandlersTestEngine()),
    );

    expect($classes)->toBe($isResource
        ? resourceExpressionHandlerOrder()
        : array_values(array_diff(resourceExpressionHandlerOrder(), [ConditionalMethodHandler::class])));
})->with([
    'a resource' => [CommentResource::class, true],
    'a resource collection' => [PostCollection::class, true],
    'a broadcast event' => [PayloadDiffersEvent::class, false],
    'a model' => [Comment::class, false],
]);

// ToResourceHandler would type a resource as a nested key's value, where Inertia sends its response, and
// StaticCallHandler would claim a query chain before ModelFinderHandler typed the paginator it returns.
it('inserts InertiaResourcePropHandler before ToResourceHandler and ModelFinderHandler before StaticCallHandler in forInertia()', function () {
    $profile = ResourceExpressionHandlers::forInertia(ResourceExpressionHandlers::forNonResourceSubjects());
    $expected = array_values(array_diff(resourceExpressionHandlerOrder(), [ConditionalMethodHandler::class]));
    array_splice($expected, (int) array_search(ToResourceHandler::class, $expected, true), 0, [InertiaResourcePropHandler::class]);
    array_splice($expected, (int) array_search(StaticCallHandler::class, $expected, true), 0, [ModelFinderHandler::class]);

    expect(array_map(fn (ExpressionHandler $handler): string => $handler::class, $profile))->toBe($expected)
        ->and($profile[0])->toEqual(new FirstClassCallableHandler(invoked: true));
});

// Without an insertion point forInertia() would drop a page-prop handler silently, and every value that handler types.
it('throws when forInertia() is given a list without ToResourceHandler or StaticCallHandler', function (array $handlers, string $missing) {
    expect(fn () => ResourceExpressionHandlers::forInertia($handlers))
        ->toThrow(LogicException::class, "forInertia() inserts a page-prop handler before {$missing}, which the list lacks.");
})->with([
    'the model-getter list' => [fn (): array => ResourceExpressionHandlers::forModelClosures(), ToResourceHandler::class],
    'a list without StaticCallHandler' => [fn (): array => [new ToResourceHandler], StaticCallHandler::class],
]);

// Inertia calls each callable it sends, while anything else is encoded as it stands, so only a read for Inertia types
// the call; a share middleware's body read for an API resource is one of the latter.
it('types a first-class callable as its call only where Inertia reads it', function (string $subject, Reading $reading, string $type, int $warnings) {
    $now = new FuncCall(new Name('now'), [new VariadicPlaceholder]);

    expect(new ResourceAstAnalyzer(new ReflectionClass($subject), null, reading: $reading)->resolve($now)['type'])->toBe($type)
        ->and(AnalysisWarnings::all())->toHaveCount($warnings);
})->with([
    'a shared-data middleware read for Inertia' => [HandleInertiaRequests::class, Reading::Inertia, 'string', 0],
    'a shared-data middleware read for anything else' => [HandleInertiaRequests::class, Reading::Encoded, 'Record<string, never>', 1],
    'a broadcast event' => [PayloadDiffersEvent::class, Reading::Encoded, 'Record<string, never>', 1],
]);

// Ordering pin #1: both handlers claim a first-class-callable $this->when(...) —
// isThisMethodCall() matches on method name alone, ignoring args — so if ConditionalMethodHandler
// ran first it would call getArgs(), which asserts !isFirstClassCallable() and fatals.
it('tries FirstClassCallableHandler before ConditionalMethodHandler for a first-class-callable $this->when(...)', function () {
    $expr = new MethodCall(new Variable('this'), 'when', [new VariadicPlaceholder]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'Record<string, never>', 'optional' => false]);
});

// ReceiverMethodCallHandler also claims only()/except(), so the order of the two cannot decide this Pick<>, in either
// spelling.
it('answers $this->relation?->only([...]) with the Pick<> whichever of RelationFilterHandler and ReceiverMethodCallHandler runs first', function (Expr $relation) {
    $expr = new NullsafeMethodCall(
        $relation,
        'only',
        [new Arg(new Array_([
            new ArrayItem(new String_('id')),
            new ArrayItem(new String_('title')),
        ]))],
    );
    $expected = ['type' => "Pick<Post, 'id' | 'title'> | null", 'optional' => false, 'modelFqcn' => Post::class];
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);
    $swapped = new ResourceAstAnalyzer(
        new ReflectionClass(CommentResource::class),
        Comment::class,
        'toArray',
        [new ReceiverMethodCallHandler, new RelationFilterHandler],
    );

    expect($analyzer->resolve($expr))->toBe($expected)
        ->and($swapped->resolve($expr))->toBe($expected);
})->with([
    '$this->post' => fn (): Expr => new PropertyFetch(new Variable('this'), 'post'),
    '$this->resource->post' => fn (): Expr => new PropertyFetch(new PropertyFetch(new Variable('this'), 'resource'), 'post'),
]);

// A `static|null` return reflects to `unknown | null` on the related model; flooring there would split this from
// `$this->author->fresh()`'s type.
it('types an unknown-only $this->relation?->fresh() as the related model, nullable', function () {
    $expr = new NullsafeMethodCall(new PropertyFetch(new Variable('this'), 'author'), 'fresh');
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class]);
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

// Ordering pin #4: StaticCallHandler's last arm claims every StaticCall on a named class, so if it
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

    expect($analyzer->resolve($expr))->toBe(['type' => 'Record<string, never>', 'optional' => false]);
});

// Ordering pin #6: ToResourceHandler matches on the method name alone and then calls getArgs(),
// which asserts !isFirstClassCallable() — so without FirstClassCallableHandler ahead of it a
// first-class-callable $this->post->toResource(...) fatals with an AssertionError.
it('tries FirstClassCallableHandler before ToResourceHandler for $this->post->toResource(...)', function () {
    $expr = new MethodCall(new PropertyFetch(new Variable('this'), 'post'), 'toResource', [new VariadicPlaceholder]);
    $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class);

    expect($analyzer->resolve($expr))->toBe(['type' => 'Record<string, never>', 'optional' => false]);
});
