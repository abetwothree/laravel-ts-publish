<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\VariadicPlaceholder;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;

/** Resolve one expression through the handler under test, with a real engine for callbacks. */
function modelFinderResult(Expr $expr): ?array
{
    $reflection = new ReflectionClass(InertiaController::class);

    return new ModelFinderHandler()->resolve(
        $expr,
        new AnalysisScope($reflection),
        new ResourceAstAnalyzer($reflection, null, 'dashboard'),
    );
}

/** Resolve one expression statement, written with fully-qualified names, through the handler under test. */
function modelFinderType(string $php): ?string
{
    return modelFinderResult(new AstParser()->parseSource('<?php '.$php.';')[0]->expr)['type'] ?? null;
}

it('claims static calls and method calls', function () {
    expect(new ModelFinderHandler()->nodeClasses())->toBe([StaticCall::class, MethodCall::class]);
});

it('types every finder terminal against the model its chain is rooted at', function (string $terminal, string $type) {
    $expr = new MethodCall(new StaticCall(new Name(Post::class), 'query'), $terminal);

    expect(modelFinderResult($expr))->toBe(['type' => $type, 'optional' => false, 'modelFqcn' => Post::class]);
})->with([
    ['find', 'Post | null'],
    ['first', 'Post | null'],
    ['firstWhere', 'Post | null'],
    ['findOrFail', 'Post'],
    ['firstOrFail', 'Post'],
    ['sole', 'Post'],
    ['firstOrCreate', 'Post'],
    ['firstOrNew', 'Post'],
    ['create', 'Post'],
    ['make', 'Post'],
    ['updateOrCreate', 'Post'],
    ['all', 'Post[]'],
    ['get', 'Post[]'],
]);

it('types a paginating terminal as its @tolki/types paginator over the model', function (string $terminal, string $name) {
    $expr = new MethodCall(new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'latest'), $terminal);

    expect(modelFinderResult($expr))->toBe([
        'type' => $name.'<Post>',
        'optional' => false,
        'modelFqcn' => Post::class,
        'customImports' => ['@tolki/types' => [$name]],
    ]);
})->with([
    ['paginate', 'LengthAwarePaginator'],
    ['simplePaginate', 'SimplePaginator'],
    ['cursorPaginate', 'CursorPaginator'],
]);

it('types the scalar terminals without claiming a model import', function () {
    $count = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'count');
    $exists = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'exists');

    expect(modelFinderResult($count))->toBe(['type' => 'number', 'optional' => false])
        ->and(modelFinderResult($exists))->toBe(['type' => 'boolean', 'optional' => false]);
});

it('declines an unknown terminal, a non-model root, and a first-class callable', function () {
    $unknownTerminal = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'pluck');
    $nonModelRoot = new StaticCall(new Name(WarehouseResource::class), 'find', [new Arg(new Int_(1))]);
    $variableRoot = new MethodCall(new Variable('builder'), 'get');
    $firstClassCallable = new StaticCall(new Name(Post::class), 'find', [new VariadicPlaceholder]);

    expect(modelFinderResult($unknownTerminal))->toBeNull()
        ->and(modelFinderResult($nonModelRoot))->toBeNull()
        ->and(modelFinderResult($variableRoot))->toBeNull()
        ->and(modelFinderResult($firstClassCallable))->toBeNull();
});

// The terminal types against the model the query before it builds, which each step's own return names.
it('types a terminal on the query each step before it hands on', function (string $php, string $type) {
    expect(modelFinderType($php))->toBe($type);
})->with([
    'a base-query step' => ['\\Workbench\\App\\Models\\Post::query()->whereIn("id", [1])->orderBy("id")->get()', 'Post[]'],
    'a when() whose callback hands the query back' => ['\\Workbench\\App\\Models\\Post::query()->when(true, fn ($q) => $q->latest())->first()', 'Post | null'],
    'a soft-delete step' => ['\\Workbench\\App\\Models\\Post::withTrashed()->count()', 'number'],
    'a relation of a found model' => ['\\Workbench\\App\\Models\\Post::query()->firstOrFail()->comments()->get()', 'Comment[]'],
    'a singular relation' => ['\\Workbench\\App\\Models\\Post::query()->firstOrFail()->author()->first()', 'User | null'],
    'a model forwarding a terminal it lacks to a new query' => ['\\Workbench\\App\\Models\\Post::findOrFail(1)->count()', 'number'],
    'a base query, whose rows no model casts' => ['\\Workbench\\App\\Models\\Post::query()->toBase()->get()', 'Record<string, unknown>[]'],
    'a find() of a list of keys' => ['\\Workbench\\App\\Models\\Post::find([1, 2])', 'Post[]'],
    'a cursor' => ['\\Workbench\\App\\Models\\Post::cursor()', 'Post[]'],
    'a custom builder\'s own method' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\BuiltPost::titled()->get()', 'BuiltPost[]'],
    'a custom builder\'s method declared as Builder' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\BuiltPost::published()->paginate()', 'LengthAwarePaginator<BuiltPost>'],
    'a parent builder\'s method declared as self' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\BuiltPost::query()->withContent()->get()', 'BuiltPost[]'],
    'a base query\'s pages' => ['\\Workbench\\App\\Models\\Post::query()->toBase()->paginate()', 'LengthAwarePaginator<Record<string, unknown>>'],
    'a base query\'s simple pages' => ['\\Workbench\\App\\Models\\Post::query()->getQuery()->simplePaginate()', 'SimplePaginator<Record<string, unknown>>'],
    'a base query\'s cursor pages' => ['\\Workbench\\App\\Models\\Post::query()->toBase()->orderBy("id")->cursorPaginate()', 'CursorPaginator<Record<string, unknown>>'],
    'a builder a model\'s own static returns that Eloquent does not define' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\SearchedPost::search("x")->where("a", 1)->get()', 'SearchedPost[]'],
    'a step past a find() a variable keys, which a model answers alike' => ['\\Workbench\\App\\Models\\Post::find($ids)->first()', 'Post | null'],
    'a scope whose hand-back is unproven' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\ScopedPost::newestVia()->get()', 'ScopedPost[]'],
    'a base-query step on a relation' => ['\\Workbench\\App\\Models\\Post::query()->firstOrFail()->comments()->whereIn("id", [1])->get()', 'Comment[]'],
]);

it('types a column a query plucks or reads as the model reads it', function (string $php, string $type) {
    expect(modelFinderType($php))->toBe($type);
})->with([
    ['\\Workbench\\App\\Models\\Post::query()->pluck("id")', 'number[]'],
    ['\\Workbench\\App\\Models\\Post::pluck("title")', 'string[]'],
    ['\\Workbench\\App\\Models\\Post::query()->latest()->value("title")', 'string | null'],
    ['\\Workbench\\App\\Models\\Post::query()->firstOrFail()->comments()->pluck("content")', 'string[]'],
]);

it('types a collection step on a list the query returns', function (string $php, string $type) {
    expect(modelFinderType($php))->toBe($type);
})->with([
    ['\\Workbench\\App\\Models\\Post::query()->pluck("id")->first()', 'number | null'],
    ['\\Workbench\\App\\Models\\Post::query()->pluck("id")->last(fn ($id) => $id > 1)', 'number | null'],
    ['\\Workbench\\App\\Models\\Post::query()->pluck("id")->all()', 'number[]'],
    ['\\Workbench\\App\\Models\\Post::query()->pluck("id")->count()', 'number'],
    ['\\Workbench\\App\\Models\\Post::query()->get()->first()', 'Post | null'],
    ['\\Workbench\\App\\Models\\Post::query()->get()->get(0)', 'Post | null'],
    ['\\Workbench\\App\\Models\\Post::query()->get()->find(1)', 'Post | null'],
    ['\\Workbench\\App\\Models\\Post::query()->get()->sole()', 'Post'],
    ['\\Workbench\\App\\Models\\Post::all()->pluck("id")->values()', 'number[]'],
]);

// "Never guess": a step whose return the engine cannot type, or that leaves the query, ends what the handler reads.
it('declines a chain past a step it cannot follow', function (string $php) {
    expect(modelFinderType($php))->toBeNull();
})->with([
    'a when() whose callback returns a count' => ['\\Workbench\\App\\Models\\Post::query()->when(true, fn ($q) => $q->count())->get()'],
    'a call no query runs' => ['\\Workbench\\App\\Models\\Post::query()->notAQueryMethod()->get()'],
    'a find() of a key no literal spells' => ['\\Workbench\\App\\Models\\Post::find($id)->comments()->get()'],
    'a collection step it does not list' => ['\\Workbench\\App\\Models\\Post::query()->pluck("id")->map(fn ($id) => $id)->first()'],
    'a pluck keyed by a second column' => ['\\Workbench\\App\\Models\\Post::query()->pluck("title", "id")->first()'],
    'a first() passing a default' => ['\\Workbench\\App\\Models\\Post::query()->get()->first(null, 0)'],
    'a factory row' => ['\\Workbench\\App\\Models\\User::factory()->raw()'],
    'a model\'s own static named like a terminal' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\ShadowingPost::cursor()'],
    'a step past a find() a variable keys, which a model answers otherwise' => ['\\Workbench\\App\\Models\\Post::find($ids)->all()'],
    'a step past a build a variable counts, which a model answers otherwise' => ['\\Workbench\\App\\Models\\User::factory()->count($n)->make()->last()'],
    'a step past a scope that provably returns a count' => ['\\AbeTwoThree\\LaravelTsPublish\\Tests\\Fixtures\\Models\\ScopedPost::tallied()->get()'],
]);

// R8: a count() or times() step, or a number passed to factory(), makes make() and create() build a list; a count no
// literal spells may build either.
it('types a factory build as a list once a count is set, else as one model', function (string $php, string $type) {
    expect(modelFinderType($php))->toBe($type);
})->with([
    ['\\Workbench\\App\\Models\\User::factory()->make()', 'User'],
    ['\\Workbench\\App\\Models\\User::factory()->state(["name" => "a"])->create()', 'User'],
    ['\\Workbench\\App\\Models\\User::factory()->count(2)->make()', 'User[]'],
    ['\\Workbench\\App\\Models\\User::factory()->times(3)->unverified()->create()', 'User[]'],
    ['\\Workbench\\App\\Models\\User::factory(2)->make()', 'User[]'],
    ['\\Workbench\\App\\Models\\User::factory(["name" => "a"])->make()', 'User'],
    ['\\Workbench\\App\\Models\\User::factory()->count(2)->count(null)->make()', 'User'],
    ['\\Workbench\\App\\Models\\User::factory()->count(2)->makeOne()', 'User'],
    ['\\Workbench\\App\\Models\\User::factory()->count(2)->make()->first()', 'User | null'],
    ['\\Workbench\\App\\Models\\User::factory()->count($n)->make()', 'User | User[]'],
    ['\\Workbench\\App\\Models\\User::factory($n)->create()', 'User | User[]'],
    ['\\Workbench\\App\\Models\\User::factory()->count($n)->make()->first()', 'User | null'],
    ['\\Workbench\\App\\Models\\User::factory()->forEachSequence(["name" => "a"])->make()', 'User[]'],
]);

// Inertia json_encodes a builder it is handed as is, which writes its one public property.
it('types a model query sent with no terminal as the builder object', function (string $php) {
    expect(modelFinderType($php))->toBe('{ pendingAttributes: Record<string, unknown> | never[] }');
})->with([
    ['\\Workbench\\App\\Models\\Post::query()'],
    ['\\Workbench\\App\\Models\\Post::where("id", 1)->latest()'],
]);
