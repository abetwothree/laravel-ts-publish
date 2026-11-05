<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\KnownFunctionCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\CollectionPipelineHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values\VariableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ArrayMapSourcesResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CollectPipelineRootsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EmptyListPipelineResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\EnumCaseHelperResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\EnumCaseHelperService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostValuesResource;
use Illuminate\Http\Request;
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

// A root read as a top-level union declines: sortBy() leaves `Comment[] | Record<string, Comment>`,
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

// A `list{}` return types as the empty tuple `[]`, which holds no element: the pipeline reads it as the empty list.
test('a collect() root typed as the empty tuple pipes the empty list', function () {
    $props = collect(resolve(AstEngine::class)->analyze(EmptyListPipelineResource::class)->properties)
        ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

    expect($props->all())->toBe([
        'values' => 'never[]',
        'all' => 'never[]',
        'mapped_const' => 'number[]',
        'mapped_same' => 'never[]',
    ]);
});

// Where the receiver resolver names no element for the root's argument, such as an array of models, the model its value
// carries binds instead.
test('a collect() root binds its map parameter to the model its value carries', function (string $php, string $type) {
    $scope = collectionPipelineScope();

    expect(collectionPipelineEngine($scope)->resolve(collectionPipelineExpr($php))['type'])->toBe($type);
})->with([
    'the relation\'s all()' => ['collect($this->comments->all())->map(fn ($c) => $c->content)->all()', 'string[]'],
    'a whenLoaded() value' => ['collect($this->whenLoaded(\'comments\'))->map(fn ($c) => ["id" => $c->id])->all()', '{ id: number }[]'],
    'a spread of the element' => ['collect($this->comments->all())->map(fn ($c) => [...$c->toArray(), "f" => 1])->all()', "(Omit<Comment, 'f'> & { f: number })[]"],
]);

// map() keeps the keys of the array collect() wraps, and calls any callable form with each element.
test('a collect() root maps any array through any callable, keeping its keys', function (string $php, string $type) {
    $expr = collectionPipelineExpr($php);

    expect(new ResourceAstAnalyzer(new ReflectionClass(ArrayMapSourcesResource::class), Post::class)->resolve($expr)['type'])
        ->toBe($type);
})->with([
    'a Record' => ['collect($this->scores)->map(fn ($n) => $n * 2)->all()', 'Record<string, number>'],
    'a string callable' => ["collect(\$this->names())->map('strtoupper')->all()", 'string[]'],
    'a first-class callable' => ['collect($this->names())->map(strtoupper(...))->all()', 'string[]'],
    'a first-class method' => ['collect($this->names())->map($this->shout(...))->all()', 'string[]'],
    'a vague root, typed callback' => [
        "collect(\$this->opaque())->map(fn (\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\ValueObjects\\MapOption \$c) => ['id' => \$c->itemId])->all()",
        '{ id: number }[] | Record<string, { id: number }>',
    ],
]);

// VariableHandler maps a collection no chain handler takes, such as a relation's plucked values, with the same typer.
test('a map() over any other collection calls any callable form', function (string $php, string $type) {
    $expr = collectionPipelineExpr($php);

    expect(new ResourceAstAnalyzer(new ReflectionClass(ArrayMapSourcesResource::class), Post::class)->resolve($expr)['type'])
        ->toBe($type);
})->with([
    'a string callable' => ["\$this->comments->pluck('content')->map('strtoupper')->all()", 'string[]'],
    'a first-class method' => ["\$this->comments->pluck('content')->map(\$this->shoutUntyped(...))->all()", 'string[]'],
    'a closure' => ["\$this->comments->pluck('content')->map(fn (\$c) => strtoupper(\$c))->all()", 'string[]'],
]);

// A pipeline root keeps a literal holding a `when*()` value, as Laravel's filter never reaches into the Collection; a
// kept Collection writes its keys as they are, where `all()` hands the resource an array it re-indexes.
test('a collect() pipeline keeps a root holding a when() value, and a kept map its int keys', function () {
    expect(publishedResourceKeyTypes(CollectPipelineRootsResource::class, Post::class))->toMatchArray([
        'when_all' => 'number[]',
        'when_values' => 'number[]',
        'when_map_identity' => 'number[]',
        'when_map_const' => 'string[]',
        'when_filter' => 'number[] | Record<string, number>',
        'when_loaded_map' => 'number[]',
        'gapped_kept' => '{ "1": string; "3": string }',
        'gapped_all' => 'string[]',
    ])->and(json_encode(new CollectPipelineRootsResource(new Post)->resolve(Request::create('/'))))->toContain(
        '"when_all":[1],"when_values":[2],"when_map_identity":[2],"when_map_const":["x","x"],"when_filter":[2],'
        .'"when_loaded_map":[1],"gapped_kept":{"1":"A","3":"B"},"gapped_all":["A","B"]',
    );
});

// The body fallback reads a bare case as its literal, yet a mapped case is still the enum its callback reads.
test('a helper\'s map() or array_map() over enum cases binds its untyped parameter to the enum', function (string $method, string $type) {
    expect(resolve(MethodReturnTypeResolver::class)->resolve(EnumCaseHelperService::class, $method)['type'] ?? null)->toBe($type);
})->with([
    'array_map()' => ['mappedGrades', '{ m: GradeType[] }'],
    'collect()->map()->all()' => ['collectedStatuses', '{ m: StatusType[] }'],
    'the cases alone, as the literals they send' => ['gradeCases', "{ m: ('pass' | 'fail')[] }"],
    'a callback reading no member, which keeps the literal' => ['identity', '{ m: 0[] }'],
]);

// A vague helper's body fallback hands its case over as a literal, so the callback reads it again as its enum.
test('a map() over the case a vague helper returns reads it as its enum', function () {
    expect(resourceProps(EnumCaseHelperResource::class))->toMatchArray([
        'collected_values' => 'StatusType[]',
        'collected_icons' => 'string[]',
        'collected_resources' => 'AsEnum<typeof Status>[]',
        'collected_outer_resources' => 'AsEnum<typeof Status>[]',
        'collected_shape_values' => '{ s: StatusType }',
    ]);
});
