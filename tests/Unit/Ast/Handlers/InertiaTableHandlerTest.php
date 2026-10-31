<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaTableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaInlineTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTableBesidePropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTraitTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostTableCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\ModellessTable;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTable;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Routing\Router;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Models\Post;

// No test here has an oracle: the stubbed table package throws in toArray(), which Inertia calls to send a table.

it('types a table prop beside the render call\'s other props, under the action\'s casts', function () {
    app(Router::class)->get('/tables/beside', [InertiaTableBesidePropsController::class, 'index']);
    $page = pageData(InertiaTableBesidePropsController::class.'@index');
    $imports = new RouteTransformer(InertiaTableBesidePropsController::class)->typeImports;

    expect($page['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post>, heading: "fixed", count: number }')
        ->and($page['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']])
        ->and($page['classFqcns'])->toContain(Post::class)
        ->and($imports)->toBe([
            '@inertiaui/table-vue' => ['TableResource'],
            '../../../../../../workbench/app/models' => ['Post'],
        ]);
});

it('types a table a local holds and a new of one', function () {
    expect(pageData(InertiaTableBesidePropsController::class.'@held')['pageType'])
        ->toBe('Inertia.SharedData & { posts: TableResource<Post>, drafts: TableResource<Post>, count: number }');
});

// A resource's own type reads its table as json_encode() sends it, an Arrayable object; Inertia sends its toArray().
it('restates a table key over a resolved resource\'s own type', function () {
    $page = pageData(InertiaTableBesidePropsController::class.'@resolved');

    expect($page['pageType'])
        ->toBe("Inertia.SharedData & { card: Omit<PostTableCardResource, 'posts'> & { posts: TableResource<Post> } }")
        ->and($page['classFqcns'])->toBe([PostTableCardResource::class, Post::class])
        ->and($page['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

it('types a table a middleware shares', function () {
    expect(sharedProps(MiddlewareWithTable::class))->toBe(['posts' => 'TableResource<Post>', 'count' => 'number']);
});

it('detects direct Inertia UI Table props without calling toArray', function () {
    $result = pageData(InertiaTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

it('detects direct Inertia UI Table props on an action a trait in another file supplies', function () {
    $result = pageData(InertiaTraitTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

it('detects service-layer Inertia UI Table props through the collaborator they come from', function () {
    $result = pageData(InertiaTableController::class.'@service');

    expect($result)->not->toBeNull()
        ->and($result['component'])->toBe('Tables/Index')
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class])
        ->and($result['externalImports'])->toBe(['@inertiaui/table-vue' => ['TableResource']]);
});

it('resolves the model from a query() method when $resource has no default', function () {
    $result = pageData(InertiaTableController::class.'@queryBased');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['classFqcns'])->toBe([Post::class]);
});

it('uses the configured React table package when set', function () {
    config()->set('ts-publish.inertia.ui_table_package', '@inertiaui/table-react');

    $result = pageData(InertiaTableController::class.'@direct');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($result['externalImports'])->toBe(['@inertiaui/table-react' => ['TableResource']]);
});

it('imports no table type for a page with no table props', function () {
    $result = pageData(InertiaController::class.'@dashboard');

    expect($result['pageType'])->not->toContain('TableResource')
        ->and($result['externalImports'])->toBe([]);
});

it('returns null for missing actions', function () {
    expect(pageData(InertiaTableController::class.'@missing'))->toBeNull();
});

it('detects an inline table on a controller whose sibling action renders no table', function () {
    $result = pageData(InertiaInlineTableController::class.'@index');
    $sibling = pageData(InertiaInlineTableController::class.'@form');

    expect($result)->not->toBeNull()
        ->and($result['pageType'])->toBe('Inertia.SharedData & { posts: TableResource<Post> }')
        ->and($sibling['pageType'])->toBe('Inertia.SharedData & { mode: string }')
        ->and($sibling['externalImports'])->toBe([]);
});

// Each falls through to the handlers after it, which type every one of these `unknown`, as before this handler.
it('declines a table naming no model, a chain on no table, and a receiver it cannot name', function (string $php) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $scope = new AnalysisScope(new ReflectionClass(InertiaController::class));

    expect(new InertiaTableHandler()->resolve($expr, $scope, chainHandlersThrowingEngine()))->toBeNull();
})->with([
    'a table naming no model' => ['\\'.ModellessTable::class.'::make()->defaultSort("-id")'],
    'a new of one' => ['new \\'.ModellessTable::class],
    'a model query' => ['\\'.Post::class.'::query()->latest()'],
    'a receiver' => ['$tables->defaultSort("-id")'],
]);
