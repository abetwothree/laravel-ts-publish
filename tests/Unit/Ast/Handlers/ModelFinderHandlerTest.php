<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
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
