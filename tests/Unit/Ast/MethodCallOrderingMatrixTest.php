<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

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
 * Whether $handler alone answers $expr non-null, isolated from any partner — the measure of "the
 * corpus exercises this pair" a vacuous-pair check needs. A handler's own internal recursion into
 * $engine->resolve() for some unrelated sub-expression must not count as claiming $expr itself.
 *
 * The engine is given the very scope the handler receives, as production does. With two separate
 * scopes a handler that seeds a binding and then recurses — VariableHandler's map() arm,
 * CollectionPipelineHandler's — reads back an unseeded scope and can never claim anything.
 */
function methodCallHandlerClaims(ExpressionHandler $handler, MethodCall $expr): bool
{
    $scope = new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);
    $engine = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class, 'toArray', [$handler], $scope);

    return $handler->resolve($expr, $scope, $engine) !== null;
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

/** make() ignores the engine today; a throwing one keeps that true if it ever changes. */
function inertEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('the matrix never resolves through make()');
        }

        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('the matrix never resolves through make()');
        }

        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('the matrix never resolves through make()');
        }
    };
}

/** @return list<ExpressionHandler> the MethodCall claimants, in registration order */
function methodCallClaimants(): array
{
    return array_values(array_filter(
        ResourceExpressionHandlers::make(inertEngine()),
        fn (ExpressionHandler $h): bool => in_array(MethodCall::class, $h->nodeClasses(), true),
    ));
}

/** @return array<string, array{0: string, 1: string}> unordered pairs, keyed for the dataset label */
function methodCallClaimantPairs(): array
{
    $claimants = methodCallClaimants();
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

it('agrees in both orders, or is pinned in the documented direction', function (string $first, string $second) {
    $byClass = fn (string $class): ExpressionHandler => array_values(array_filter(
        methodCallClaimants(),
        fn (ExpressionHandler $h): bool => $h::class === $class,
    ))[0];
    // $a is whichever of the pair handlers() CURRENTLY lists first — [$a, $b] therefore always
    // reproduces the real production dispatch order for this pair, whatever that order is today.
    [$a, $b] = [$byClass($first), $byClass($second)];

    $run = function (array $profile, MethodCall $expr): array {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), Comment::class, 'toArray', $profile);

        try {
            return $analyzer->resolve($expr);
        } catch (Throwable $e) {
            return ['type' => 'THROWS '.$e::class];
        }
    };

    $sortedBasenames = array_map(class_basename(...), [$first, $second]);
    sort($sortedBasenames);
    $pairKey = implode('|', $sortedBasenames);
    $winner = METHOD_CALL_PINNED[$pairKey] ?? null;
    $disagreed = false;

    foreach (methodCallCorpus() as $expr) {
        $ab = $run([$a, $b], $expr);
        $ba = $run([$b, $a], $expr);

        if ($ab !== $ba) {
            $disagreed = true;
            expect($winner)
                ->not->toBeNull("Unpinned pair {$pairKey} disagrees on ".get_debug_type($expr).': '.json_encode([$ab, $ba]));

            // $ab is production's real answer (see [$a, $b] above); it must equal the pinned winner
            // run first, not merely "some" disagreement was pinned — this is what catches handlers()
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
            methodCallCorpus(),
            fn (MethodCall $expr): bool => methodCallHandlerClaims($a, $expr) || methodCallHandlerClaims($b, $expr),
        );
        expect($claimed)->toBeTrue("Pair {$pairKey} is vacuous — neither handler claimed any corpus expression");
    }
})->with(methodCallClaimantPairs());
