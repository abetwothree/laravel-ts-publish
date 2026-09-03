<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ConditionalMethodHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ToResourceHandler;
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
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/**
 * Every disagreeing unordered pair of MethodCall claimants, keyed by its two class basenames sorted
 * alphabetically, naming the class that must win. Every other pair is inert; one entry here is a
 * questionable-but-real winner — see docs/known-gaps.md for which, and why.
 */
const PINNED = [
    'ConditionalMethodHandler|FirstClassCallableHandler' => FirstClassCallableHandler::class,
    'FirstClassCallableHandler|ToResourceHandler' => FirstClassCallableHandler::class,
    'FirstClassCallableHandler|KnownFunctionCallHandler' => FirstClassCallableHandler::class,
    'ConditionalMethodHandler|RelationCollectionChainHandler' => ConditionalMethodHandler::class,
    'RelationCollectionChainHandler|ToResourceHandler' => ToResourceHandler::class,
    'RelationCollectionChainHandler|RelationFilterHandler' => RelationFilterHandler::class,
    'KnownMethodRuleHandler|RelationCollectionChainHandler' => RelationCollectionChainHandler::class,
];

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
        new MethodCall(new MethodCall($this_, 'comments'), 'pluck', [new Arg(new String_('id'))]),
        new MethodCall(new Variable('request'), 'ip', []),
        new MethodCall(new Variable('request'), 'user', []),
        new MethodCall(new StaticCall(new Name('Auth'), 'guard'), 'user', []),
        new MethodCall(new FuncCall(new Name('config')), 'integer', [new Arg(new String_('k')), new Arg(new Int_(0))]),
        new MethodCall(new Variable('items'), 'map', [new VariadicPlaceholder]),
        new MethodCall(new FuncCall(new Name('auth')), 'user', [new VariadicPlaceholder]),
        new MethodCall(new StaticCall(new Name('Whatever'), 'collection', []), 'resolve', []),
        new MethodCall($this_, 'can', [new Arg(new String_('edit'))]),
        new MethodCall(new Variable('users'), 'map', [new Arg(new ArrowFunction([
            'params' => [new Param(new Variable('user'), type: new Name(User::class))],
            'expr' => new PropertyFetch(new Variable('user'), 'name'),
        ]))]),
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

        public function returnArrayAnalysis(Array_ $array): MethodAnalysis
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
    $winner = PINNED[$pairKey] ?? null;
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
        // An unpinned disagreement already failed inside the loop above; this only gives the inert
        // case its own assertion — phpunit.xml.dist's failOnRisky would otherwise fail a dataset
        // entry that never disagreed, since it would run to completion without asserting anything.
        expect($disagreed)->toBeFalse("Unpinned pair {$pairKey} disagreed without failing above — investigate");
    }
})->with(methodCallClaimantPairs());
