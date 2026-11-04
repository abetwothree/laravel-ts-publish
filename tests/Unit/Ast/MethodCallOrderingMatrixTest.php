<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaTableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\ClosureHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Cog;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\ModellessTable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostQueryTable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostTable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;

/**
 * Every disagreeing unordered pair of MethodCall claimants, keyed by its two class basenames sorted
 * alphabetically, naming the class that must win. Every other pair is inert.
 */
const METHOD_CALL_PINNED = [
    'ConditionalMethodHandler|FirstClassCallableHandler' => FirstClassCallableHandler::class,
    'FirstClassCallableHandler|ToResourceHandler' => FirstClassCallableHandler::class,
    'FirstClassCallableHandler|KnownFunctionCallHandler' => FirstClassCallableHandler::class,
];

/**
 * The Inertia profile's disagreeing pairs, keyed and named as in METHOD_CALL_PINNED. Its half runs only the pairs
 * with a handler the resource profile lacks, since every other pair keeps the resource profile's order.
 */
const INERTIA_METHOD_CALL_PINNED = [
    'FirstClassCallableHandler|InertiaResourcePropHandler' => FirstClassCallableHandler::class,
    'InertiaResourcePropHandler|ToResourceHandler' => InertiaResourcePropHandler::class,
];

/**
 * Whether $handler alone answers $expr non-null, isolated from any partner: its own recursion into $engine->resolve()
 * for an unrelated sub-expression must not count as claiming $expr. The engine shares the handler's scope, as in
 * production, or a handler that seeds a binding and then recurses could never claim anything.
 *
 * @param  Closure(): AnalysisScope  $newScope
 */
function methodCallHandlerClaims(ExpressionHandler $handler, MethodCall $expr, Closure $newScope): bool
{
    $scope = $newScope();
    // ClosureHandler claims no MethodCall, so it joins no pair, but every map writer reads its callback through it.
    $engine = methodCallMatrixEngine($scope, [$handler, new ClosureHandler]);

    return $handler->resolve($expr, $scope, $engine) !== null;
}

/** A fresh scope of the resource half's subject, CommentResource over Comment. */
function resourceMatrixScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
}

/** A fresh scope of a model-less controller action holding a route-bound warehouse and post, as a page's does. */
function inertiaMatrixScope(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(InertiaController::class));
    $scope->varModelBindings['warehouse'] = Warehouse::class;
    $scope->varModelBindings['post'] = Post::class;

    return $scope;
}

/**
 * An engine over the scope that runs only the given handlers.
 *
 * @param  list<ExpressionHandler>  $profile
 */
function methodCallMatrixEngine(AnalysisScope $scope, array $profile): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer($scope->subjectReflection, $scope->modelClass, handlerProfile: $profile, scope: $scope);
}

/** @return list<MethodCall> */
function methodCallCorpus(): array
{
    $this_ = new Variable('this');
    $arr = fn (array $keys) => new Array_(array_map(fn (string $k) => new ArrayItem(new String_($k)), $keys));

    return [
        new MethodCall($this_, 'when', [new VariadicPlaceholder]),
        new MethodCall($this_, 'when', [new Arg(new ConstFetch(new Name('true'))), new Arg(new String_('x'))]),
        new MethodCall($this_, 'whenLoaded', [new Arg(new String_('post'))]),
        new MethodCall(new PropertyFetch($this_, 'post'), 'toResource', [new VariadicPlaceholder]),
        new MethodCall(new PropertyFetch($this_, 'post'), 'toResource', []),
        new MethodCall(new PropertyFetch($this_, 'post'), 'only', [new Arg($arr(['id', 'title']))]),
        new MethodCall(new PropertyFetch($this_, 'post'), 'except', [new Arg($arr(['body']))]),
        // The same filters through the $this->resource proxy, on the resource's own model and on a relation.
        new MethodCall(new PropertyFetch($this_, 'resource'), 'only', [new Arg($arr(['id', 'content']))]),
        new MethodCall(new PropertyFetch(new PropertyFetch($this_, 'resource'), 'post'), 'only', [new Arg($arr(['id', 'title']))]),
        // Runtime key lists and a many-relation filtered by primary key, which only filter-aware handlers may answer.
        new MethodCall(new PropertyFetch($this_, 'post'), 'only', [new Arg(new Variable('fields'))]),
        new MethodCall(new PropertyFetch($this_, 'post'), 'except', [new Arg(new Variable('fields'))]),
        new MethodCall(new PropertyFetch($this_, 'resource'), 'except', [new Arg(new Variable('fields'))]),
        new MethodCall(new PropertyFetch($this_, 'replies'), 'only', [new Arg(new Array_([new ArrayItem(new Int_(1))]))]),
        new MethodCall(new MethodCall($this_, 'comments'), 'pluck', [new Arg(new String_('id'))]),
        new MethodCall(new Variable('request'), 'ip', []),
        new MethodCall(new Variable('request'), 'user', []),
        new MethodCall(new StaticCall(new Name('Auth'), 'guard'), 'user', []),
        new MethodCall(new FuncCall(new Name('config')), 'integer', [new Arg(new String_('k')), new Arg(new Int_(0))]),
        new MethodCall(new Variable('items'), 'map', [new VariadicPlaceholder]),
        new MethodCall(new FuncCall(new Name('auth')), 'user', [new VariadicPlaceholder]),
        new MethodCall(new StaticCall(new Name('Whatever'), 'collection', []), 'resolve', []),
        new MethodCall($this_, 'can', [new Arg(new String_('edit'))]),
        new MethodCall(new PropertyFetch($this_, 'replies'), 'first', [new VariadicPlaceholder]),
        new MethodCall(new Variable('users'), 'map', [new Arg(new ArrowFunction([
            'params' => [new Param(new Variable('user'), type: new Name(User::class))],
            'expr' => new PropertyFetch(new Variable('user'), 'name'),
        ]))]),
        // ReceiverMethodResource's method calls, then the same receiver kinds on the corpus's Comment scope.
        new MethodCall(new PropertyFetch($this_, 'priority'), 'label'),
        new MethodCall(new PropertyFetch(new PropertyFetch($this_, 'resource'), 'priority'), 'label'),
        new MethodCall(new MethodCall(new PropertyFetch(new PropertyFetch($this_, 'resource'), 'published_at'), 'setTimezone', [new Arg(new String_('UTC'))]), 'toDateString'),
        new MethodCall(new MethodCall(new PropertyFetch($this_, 'published_at'), 'setTimezone', [new Arg(new String_('UTC'))]), 'toDateString'),
        new MethodCall(new StaticCall(new Name(Priority::class), 'from', [new Arg(new Int_(1))]), 'label'),
        new MethodCall(new MethodCall(new PropertyFetch($this_, 'flagged_at'), 'setTimezone', [new Arg(new String_('UTC'))]), 'toDateString'),
        new MethodCall(new PropertyFetch($this_, 'flagged_at'), 'toDateString'),
        new MethodCall(new PropertyFetch(new PropertyFetch($this_, 'resource'), 'post'), 'getMorphClass'),
        new MethodCall(new PropertyFetch($this_, 'post'), 'fresh'),
        // A bare call the resource forwards to its model, which only ReceiverMethodCallHandler answers.
        new MethodCall($this_, 'getKey'),
        // The same key read through the $this->resource proxy.
        new MethodCall(new PropertyFetch($this_, 'resource'), 'getKey'),
        // The proxy spelling RelationCollectionChainHandler keeps, a call on a relation it hands on, and a plain chain.
        new MethodCall(new PropertyFetch($this_, 'resource'), 'getMorphClass'),
        new MethodCall(new PropertyFetch($this_, 'post'), 'commentsCount'),
        new MethodCall(new PropertyFetch($this_, 'replies'), 'take', [new Arg(new Int_(5))]),
        // The same relation pipeline rooted through the $this->resource proxy.
        new MethodCall(new PropertyFetch(new PropertyFetch($this_, 'resource'), 'replies'), 'take', [new Arg(new Int_(5))]),
        // The nullsafe proxy spellings of a relation filter and pipeline.
        new MethodCall(new NullsafePropertyFetch(new PropertyFetch($this_, 'resource'), 'replies'), 'take', [new Arg(new Int_(5))]),
        new MethodCall(new NullsafePropertyFetch(new PropertyFetch($this_, 'resource'), 'replies'), 'only', [new Arg(new Array_([new ArrayItem(new Int_(1))]))]),
        new MethodCall(new NullsafePropertyFetch(new PropertyFetch($this_, 'resource'), 'post'), 'only', [new Arg($arr(['id', 'title']))]),
        // Collection pipelines: a trailing values()->all() on a relation root, the same shape on a
        // collect() root, and concat() of the receiver's own relation.
        new MethodCall(new MethodCall(new MethodCall(new PropertyFetch($this_, 'replies'), 'map', [
            new Arg(new ArrowFunction([
                'params' => [new Param(new Variable('reply'))],
                'expr' => new PropertyFetch(new Variable('reply'), 'id'),
            ])),
        ]), 'values'), 'all'),
        new MethodCall(new MethodCall(new MethodCall(new FuncCall(new Name('collect'), [
            new Arg(new FuncCall(new Name('explode'), [new Arg(new String_(' ')), new Arg(new PropertyFetch($this_, 'content'))])),
        ]), 'map', [
            new Arg(new ArrowFunction([
                'params' => [new Param(new Variable('word'))],
                'expr' => new Array_([new ArrayItem(new Variable('word'), new String_('word'))]),
            ])),
        ]), 'values'), 'all'),
        new MethodCall(new MethodCall(new PropertyFetch($this_, 'replies'), 'concat', [
            new Arg(new PropertyFetch($this_, 'replies')),
        ]), 'values'),
        // A collect() root the pipeline handler can type with only its PARTNER in the profile: the
        // argument needs RelationCollectionChainHandler, not the KnownFunctionCallHandler/ClosureHandler
        // pair above, neither of which is a MethodCall claimant and so is in no pair at all.
        new MethodCall(new FuncCall(new Name('collect'), [
            new Arg(new MethodCall(new PropertyFetch($this_, 'replies'), 'pluck', [new Arg(new String_('content'))])),
        ]), 'values'),
        new MethodCall(new MethodCall(new FuncCall(new Name('collect'), [
            new Arg(new MethodCall(new PropertyFetch($this_, 'replies'), 'pluck', [new Arg(new String_('content'))])),
        ]), 'filter'), 'values'),
    ];
}

/** @return list<MethodCall> the page props the Inertia profile's own handlers claim, and the calls beside them */
function inertiaMethodCallCorpus(): array
{
    $query = fn (): StaticCall => new StaticCall(new Name(Post::class), 'query');
    $paginated = fn (): MethodCall => new MethodCall($query(), 'paginate', [new Arg(new Int_(10))]);

    return [
        new MethodCall(new Variable('warehouse'), 'toResource'),
        new MethodCall(new Variable('warehouse'), 'toResource', [new VariadicPlaceholder]),
        new MethodCall(new Variable('warehouse'), 'toResource', [new Arg(new ClassConstFetch(new Name(WarehouseResource::class), 'class'))]),
        new MethodCall(new Variable('post'), 'toResource'),
        $paginated(),
        new MethodCall($paginated(), 'toResourceCollection'),
        new MethodCall($paginated(), 'toResourceCollection', [new VariadicPlaceholder]),
        new MethodCall(new StaticCall(new Name(Post::class), 'all'), 'toResourceCollection'),
        // #[UseResource] names a collection, so the page-prop handler declines the guess; ToResourceHandler types it.
        new MethodCall(new StaticCall(new Name(Cog::class), 'all'), 'toResourceCollection'),
        new MethodCall(new StaticCall(new Name(Post::class), 'where', [new Arg(new String_('id')), new Arg(new Int_(1))]), 'first'),
        new MethodCall(new MethodCall($query(), 'latest'), 'get'),
        new MethodCall($query(), 'count'),
        new MethodCall($query(), 'exists'),
        new MethodCall(new MethodCall(new Variable('post'), 'comments'), 'count'),
        new MethodCall(new Variable('post'), 'fresh'),
        new MethodCall(new FuncCall(new Name('auth')), 'user'),
        // Inertia UI Tables, whose model a `$resource` default or a `query()` names, and one naming none.
        new MethodCall(new StaticCall(new Name(PostTable::class), 'make'), 'defaultSort', [new Arg(new String_('-id'))]),
        new MethodCall(new New_(new Name(PostQueryTable::class)), 'defaultSort'),
        new MethodCall(new StaticCall(new Name(ModellessTable::class), 'make'), 'defaultSort'),
    ];
}

/** make() ignores the engine today; a throwing one keeps that true if it ever changes. */
function inertEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('the matrix never resolves through make()');
        }

        public function spreadAnalysis(string $methodName, ReflectionClass $built): ?MethodAnalysis
        {
            throw new RuntimeException('the matrix never resolves through make()');
        }

        /** Reads every value as `json_encode()` sends it. */
        public function reading(): Reading
        {
            return Reading::Encoded;
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('the matrix never resolves through make()');
        }
    };
}

/** @return list<ExpressionHandler> the resource profile's MethodCall claimants, in registration order */
function methodCallClaimants(): array
{
    return methodCallClaimantsOf(ResourceExpressionHandlers::make(inertEngine()));
}

/** @return list<ExpressionHandler> the Inertia profile's MethodCall claimants, in registration order */
function inertiaMethodCallClaimants(): array
{
    return methodCallClaimantsOf(ResourceExpressionHandlers::forInertia(ResourceExpressionHandlers::forNonResourceSubjects()));
}

/**
 * The profile's MethodCall claimants, in its order.
 *
 * @param  list<ExpressionHandler>  $profile
 * @return list<ExpressionHandler>
 */
function methodCallClaimantsOf(array $profile): array
{
    return array_values(array_filter(
        $profile,
        fn (ExpressionHandler $h): bool => in_array(MethodCall::class, $h->nodeClasses(), true),
    ));
}

/** @return array<string, array{0: string, 1: string}> unordered pairs, keyed for the dataset label */
function methodCallClaimantPairs(): array
{
    return methodCallPairsOf(methodCallClaimants());
}

/** @return array<string, array{0: string, 1: string}> the Inertia profile's pairs with a handler only it runs */
function inertiaMethodCallClaimantPairs(): array
{
    return array_filter(
        methodCallPairsOf(inertiaMethodCallClaimants()),
        fn (array $pair): bool => array_intersect($pair, [InertiaResourcePropHandler::class, ModelFinderHandler::class, InertiaTableHandler::class]) !== [],
    );
}

/**
 * Every unordered pair of the claimants, the one listed first first.
 *
 * @param  list<ExpressionHandler>  $claimants
 * @return array<string, array{0: string, 1: string}>
 */
function methodCallPairsOf(array $claimants): array
{
    $pairs = [];

    foreach ($claimants as $i => $a) {
        foreach ($claimants as $j => $b) {
            if ($i < $j) {
                $pairs[class_basename($a).' / '.class_basename($b)] = [$a::class, $b::class];
            }
        }
    }

    return $pairs;
}

/**
 * Runs one pair over the corpus in both orders, against its pin or, unpinned, for agreement and a claim.
 *
 * @param  list<ExpressionHandler>  $claimants
 * @param  array<string, class-string<ExpressionHandler>>  $pins
 * @param  list<MethodCall>  $corpus
 * @param  Closure(): AnalysisScope  $newScope
 */
function expectMethodCallPairOrdering(string $first, string $second, array $claimants, array $pins, array $corpus, Closure $newScope): void
{
    $byClass = fn (string $class): ExpressionHandler => array_values(array_filter(
        $claimants,
        fn (ExpressionHandler $h): bool => $h::class === $class,
    ))[0];
    // $a is whichever of the pair the profile CURRENTLY lists first — [$a, $b] therefore always
    // reproduces the real production dispatch order for this pair, whatever that order is today.
    [$a, $b] = [$byClass($first), $byClass($second)];

    $run = function (array $profile, MethodCall $expr) use ($newScope): array {
        try {
            return methodCallMatrixEngine($newScope(), $profile)->resolve($expr);
        } catch (Throwable $e) {
            return ['type' => 'THROWS '.$e::class];
        }
    };

    $sortedBasenames = array_map(class_basename(...), [$first, $second]);
    sort($sortedBasenames);
    $pairKey = implode('|', $sortedBasenames);
    $winner = $pins[$pairKey] ?? null;
    $disagreed = false;

    foreach ($corpus as $expr) {
        $ab = $run([$a, $b], $expr);
        $ba = $run([$b, $a], $expr);

        if ($ab !== $ba) {
            $disagreed = true;
            expect($winner)
                ->not->toBeNull("Unpinned pair {$pairKey} disagrees on ".get_debug_type($expr).': '.json_encode([$ab, $ba]));

            // $ab is production's real answer (see [$a, $b] above); it must equal the pinned winner
            // run first, not merely "some" disagreement was pinned — this is what catches the profile
            // demoting the winner: after a reorder, $a silently becomes the loser instead.
            $winnerFirst = $winner === $a::class ? [$a, $b] : [$b, $a];
            expect($ab)->toBe($run($winnerFirst, $expr), "Pinned pair {$pairKey}: production order answered ".
                json_encode($ab).' but documented winner '.$winner.' should have won on '.get_debug_type($expr));
        }
    }

    if ($winner !== null) {
        expect($disagreed)->toBeTrue("Pinned pair {$pairKey} never disagreed on the corpus — the pin is dead");
    } else {
        // A disagreement here already failed inside the loop above. What's left to prove for an inert
        // pair is that the corpus wasn't simply silent for both handlers — see methodCallHandlerClaims().
        $claimed = array_any(
            $corpus,
            fn (MethodCall $expr): bool => methodCallHandlerClaims($a, $expr, $newScope) || methodCallHandlerClaims($b, $expr, $newScope),
        );
        expect($claimed)->toBeTrue("Pair {$pairKey} is vacuous — neither handler claimed any corpus expression");
    }
}

it('agrees in both orders, or is pinned in the documented direction', function (string $first, string $second) {
    expectMethodCallPairOrdering($first, $second, methodCallClaimants(), METHOD_CALL_PINNED, methodCallCorpus(), resourceMatrixScope(...));
})->with(methodCallClaimantPairs());

it('agrees in both orders in the Inertia profile, or is pinned in the documented direction', function (string $first, string $second) {
    expectMethodCallPairOrdering(
        $first,
        $second,
        inertiaMethodCallClaimants(),
        INERTIA_METHOD_CALL_PINNED,
        inertiaMethodCallCorpus(),
        inertiaMatrixScope(...),
    );
})->with(inertiaMethodCallClaimantPairs());
