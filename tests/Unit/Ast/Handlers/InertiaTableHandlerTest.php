<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaTableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaInlineTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTableBesidePropsController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\InertiaTraitTableController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PostTableCardResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\ModellessTable;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTable;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\MiddlewareWithTableCount;
use AbeTwoThree\LaravelTsPublish\Transformers\RouteTransformer;
use Illuminate\Routing\Router;
use Inertia\Inertia;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

// Only a value no table reaches has an oracle: the stubbed table package throws in toArray(), which Inertia calls to
// send a table.

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

// `->query()->count()` returns the number of the table's query, not the table, so it reads as that call does.
it('types a chain past the table as its last call, in a literal, a local, shared data and a resolved body', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Post::create(['title' => 'Second', 'content' => 'Body', 'user_id' => $user->id]);
    $counted = inertiaProps(new InertiaTableBesidePropsController()->counted());
    $resolved = inertiaProps(new InertiaTableBesidePropsController()->countedResolved($post));
    Inertia::share(new MiddlewareWithTableCount()->share(request()));
    $shared = inertiaProps(Inertia::render('Shared', []));

    expect(pageData(InertiaTableBesidePropsController::class.'@counted')['pageType'])
        ->toBe('Inertia.SharedData & { n: number, held: number }')
        ->and(pageData(InertiaTableBesidePropsController::class.'@countedResolved')['pageType'])
        ->toBe('Inertia.SharedData & { card: PostTableCountResource }')
        ->and(sharedProps(MiddlewareWithTableCount::class))->toBe(['n' => 'number', 'ids' => 'number[]'])
        ->and([$counted->n, $counted->held, $resolved->card->n, $shared->n, $shared->ids])->toBe([2, 2, 2, 2, [1, 2]]);
});

it('types a props local holding a chain past the table as its last call', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Post::create(['title' => 'Second', 'content' => 'Body', 'user_id' => $user->id]);

    expect(pageData(InertiaTableBesidePropsController::class.'@countedLocal')['pageType'])
        ->toBe('Inertia.SharedData & { h: string, n: number }')
        ->and(inertiaProps(new InertiaTableBesidePropsController()->countedLocal())->n)->toBe(2);
});

// The table's query() is a query on its model, so a chain past it reads as the same chain on `Post::query()`.
it('types a chain past the table\'s query() as that chain on the model\'s query()', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Post::create(['title' => 'Second', 'content' => 'Body', 'user_id' => $user->id]);
    $sent = inertiaProps(new InertiaTableBesidePropsController()->queried());

    expect(pageData(InertiaTableBesidePropsController::class.'@queried')['pageType'])
        ->toBe('Inertia.SharedData & { rows: Post[], first: Post | null }')
        ->and(array_column($sent->rows, 'title'))->toBe(['First', 'Second'])
        ->and($sent->first->title)->toBe('Second');
});

// `query()` queries User though `$resource` names Post, so the chain past it is a User query.
it('reads a chain past the table\'s query() on the model that query() queries', function () {
    User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $sent = inertiaProps(new InertiaTableBesidePropsController()->mixedQueried());

    expect(pageData(InertiaTableBesidePropsController::class.'@mixedQueried')['pageType'])
        ->toBe('Inertia.SharedData & { rows: User[], first: User | null }')
        ->and(array_column($sent->rows, 'name'))->toBe(['Ada'])
        ->and($sent->first->name)->toBe('Ada');
});

// The table's query plucks the column as the model reads it, and Inertia json_encodes the query itself as its one
// public property.
it('types a pluck() past the table\'s query() and the query itself as Inertia sends them', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);
    Post::create(['title' => 'Second', 'content' => 'Body', 'user_id' => $user->id]);
    $sent = inertiaProps(new InertiaTableBesidePropsController()->plucked());

    expect(pageData(InertiaTableBesidePropsController::class.'@plucked')['pageType'])->toBe('Inertia.SharedData & { '
        .'ids: number[], query: { pendingAttributes: Record<string, unknown> | never[] }, held: number[] }')
        ->and([$sent->ids, $sent->held, $sent->query->pendingAttributes])->toBe([[1, 2], [1, 2], []]);
});

// Nothing types an untyped method that hands the table back. In a render call's props literal, where the reader this
// replaced read it as the table, it keeps that type; elsewhere it stays `unknown`. Both props hold the table, whose
// stubbed toArray() throws, so no oracle runs.
it('keeps the table type of a chain past the table that nothing else reads where it always had it', function () {
    expect(pageData(InertiaTableBesidePropsController::class.'@untyped')['pageType'])
        ->toBe('Inertia.SharedData & { posts: TableResource<Post>, held: unknown }');
});

// The table type a props literal's item keeps is marked on the node the page analyzer reads, so it holds after the
// parse cache drops the file, which one page analyzed per route meets in a large app.
it('keeps the table type of a props literal item past the table after the parse cache drops the file', function () {
    $evict = function (): void {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            dirname(__DIR__, 4).'/vendor/laravel/framework/src/Illuminate',
            FilesystemIterator::SKIP_DOTS,
        ));
        $parsed = 0;

        foreach ($files as $file) {
            if ($parsed < 300 && str_ends_with((string) $file, '.php')) {
                resolve(AstParser::class)->parseFile((string) $file);
                $parsed++;
            }
        }
    };
    $type = 'Inertia.SharedData & { posts: TableResource<Post>, held: unknown }';
    resolve(MethodLocator::class)->locateDeclared(InertiaTableBesidePropsController::class, 'untyped');
    $evict();
    $afterLocated = pageData(InertiaTableBesidePropsController::class.'@untyped')['pageType'];
    $evict();

    expect($afterLocated)->toBe($type)
        ->and(pageData(InertiaTableBesidePropsController::class.'@untyped')['pageType'])->toBe($type);
});

// A static root other than make() is a call like any other: the table where it says it returns one.
it('reads a static root other than make() as the call it is', function () {
    expect(pageData(InertiaTableBesidePropsController::class.'@staticTotal')['pageType'])->toBe('Inertia.SharedData & { n: number }')
        ->and(inertiaProps(new InertiaTableBesidePropsController()->staticTotal())->n)->toBe(4)
        ->and(pageData(InertiaTableBesidePropsController::class.'@staticNamed')['pageType'])
        ->toBe('Inertia.SharedData & { posts: TableResource<Post> }');
});

// A docblock's short name and alias are read against the declaring file's use statements, as PHP reads them.
it('reads a table an untyped method returns by its docblock\'s short name or alias', function () {
    expect(pageData(InertiaTableBesidePropsController::class.'@docblocked')['pageType'])
        ->toBe('Inertia.SharedData & { short: TableResource<Post>, aliased: TableResource<Post> }')
        ->and(pageData(InertiaTableBesidePropsController::class.'@docblockTotal')['pageType'])
        ->toBe('Inertia.SharedData & { total: number }')
        ->and(inertiaProps(new InertiaTableBesidePropsController()->docblockTotal())->total)->toBe(3);
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
