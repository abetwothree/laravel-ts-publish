<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\CollectionPipelineHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\VariableHandler;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostValuesResource;
use PhpParser\Node\Expr;
use Workbench\App\Http\Resources\CollectionPipelineResource;
use Workbench\App\Models\Post;

/** Parse one expression statement. */
function collectionPipelineExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/** A Post-backed scope on the pipeline fixture. */
function collectionPipelineScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(CollectionPipelineResource::class), Post::class);
}

/** The full resource profile over a scope the caller also holds, the way production shares one. */
function collectionPipelineEngine(AnalysisScope $scope): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer(new ReflectionClass(CollectionPipelineResource::class), Post::class, 'toArray', null, $scope);
}

test('collection pipelines keep their element types to the end of the chain', function () {
    $props = collect(new ResourceAstAnalyzer(new ReflectionClass(CollectionPipelineResource::class), Post::class)->analyze()->properties)->keyBy('name');

    expect($props['comment_ids']['type'])->toBe('number[]')
        ->and($props['title_words']['type'])->toBe('{ word: string }[]')
        ->and($props['author_name']['type'])->toBe('string | null')
        ->and($props['author_name_or_guest']['type'])->toBe('string | null')
        ->and($props['doubled']['type'])->toBe('Comment[]')
        ->and($props['typed']['type'])->toBe('{ id: number }[]')
        ->and($props['typed']['optional'])->toBeTrue();
});

test('data_get declines a wildcard key', function () {
    $scope = collectionPipelineScope();
    $expr = collectionPipelineExpr('data_get($this->comments, "*.id")');

    expect(new KnownFunctionCallHandler()->resolve($expr, $scope, collectionPipelineEngine($scope)))->toBeNull();
});

// The inert half of the ordering inventory's MethodCall row: both handlers claim a trailing `values()` on a collect()
// chain, and the peel reads a receiver one op shorter, so its keyed arm must become the list the pipeline gives.
test('the values() peel and the pipeline handler agree on a filtered collect() chain', function () {
    $scope = collectionPipelineScope();
    $engine = collectionPipelineEngine($scope);
    $expr = collectionPipelineExpr('collect($this->comments->pluck(\'id\'))->filter()->values()');

    $pipeline = new CollectionPipelineHandler()->resolve($expr, $scope, $engine);
    $peel = new VariableHandler()->resolve($expr, $scope, $engine);

    // values() restores 0..n-1, so the sequential arm alone is the accurate answer.
    expect($pipeline['type'])->toBe('number[]')
        ->and($peel)->toBe($pipeline);
});

// all() hands back the underlying array with its keys untouched, so it stays pure identity — the
// keyed arm genuinely survives it, and dropping it there would be the opposite error.
test('the all() peel keeps a keyed arm the receiver really carries', function () {
    $scope = collectionPipelineScope();
    $engine = collectionPipelineEngine($scope);
    $expr = collectionPipelineExpr('collect($this->comments->pluck(\'id\'))->filter()->all()');

    expect(new VariableHandler()->resolve($expr, $scope, $engine)['type'])
        ->toBe('number[] | Record<string, number>');
});

// values() re-indexes a keyed collection into a list, and all() hands its array back; a class that is not a collection
// answers both from its own signatures, whatever its public properties are.
test('a trailing values() or all() keeps the receiver\'s type only on a collection', function () {
    $props = collect(resolve(AstEngine::class)->analyze(PostValuesResource::class)->properties)
        ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

    expect($props->all())->toBe([
        'values' => 'number[]',
        'all' => 'Record<string, number>',
        'values_all' => 'number[]',
        'settings_values' => '(string | number)[]',
        'settings_all' => 'Record<string, string>',
    ]);
});

test('a collect() root whose keys broke carries the keyed Record arm', function () {
    $scope = collectionPipelineScope();
    $engine = collectionPipelineEngine($scope);
    $expr = collectionPipelineExpr('collect($this->comments->pluck(\'id\'))->filter()');

    expect(new CollectionPipelineHandler()->resolve($expr, $scope, $engine)['type'])
        ->toBe('number[] | Record<string, number>');
});

// elementTypeOf() declines a top-level union: sortBy() leaves `Comment[] | Record<string, Comment>`,
// and which arm the elements came from is exactly what a union does not say.
test('a collect() root resolving to a top-level union declines', function () {
    $scope = collectionPipelineScope();
    $engine = collectionPipelineEngine($scope);

    expect($engine->resolve(collectionPipelineExpr('$this->comments->sortBy(\'id\')'))['type'])
        ->toBe('Comment[] | Record<string, Comment>')
        ->and(new CollectionPipelineHandler()->resolve(
            collectionPipelineExpr('collect($this->comments->sortBy(\'id\'))->values()'),
            $scope,
            $engine,
        ))->toBeNull();
});
