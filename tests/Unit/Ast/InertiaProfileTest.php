<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Calls\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\Inertia\InertiaTableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\PostReceiverController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostTable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Middleware\HandleInertiaRequests;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;

/** An analyzer reading what Inertia sends, with no backing model, as the page analyzer builds it. */
function inertiaProfileAnalyzer(): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer(new ReflectionClass(InertiaController::class), null, 'dashboard', reading: Reading::Inertia);
}

// Inertia sends a page prop as it sends a shared value, so a page and shared data run one profile; the flag decides
// it, not the class, so a share middleware's helper an API resource reads runs that resource's profile.
it('gives a page and a shared-data middleware the one Inertia profile, and only where Inertia reads', function () {
    $engine = inertiaProfileAnalyzer();
    $inertia = ResourceExpressionHandlers::forInertia(ResourceExpressionHandlers::forNonResourceSubjects());

    expect(ResourceExpressionHandlers::forSubject(InertiaController::class, $engine, reading: Reading::Inertia))->toEqual($inertia)
        ->and(ResourceExpressionHandlers::forSubject(HandleInertiaRequests::class, $engine, reading: Reading::Inertia))->toEqual($inertia)
        ->and(ResourceExpressionHandlers::forSubject(HandleInertiaRequests::class, $engine))
        ->toEqual(ResourceExpressionHandlers::forNonResourceSubjects())
        ->and(ResourceExpressionHandlers::forSubject(WarehouseResource::class, $engine, reading: Reading::Inertia))
        ->toEqual(ResourceExpressionHandlers::forInertia(ResourceExpressionHandlers::make($engine)))
        ->and($inertia)->toHaveCount(30);
});

// An Inertia reading's CallSite binds a callable as called, which only the profile the reading builds reads as called.
it('takes an injected profile or an Inertia reading, never both', function (Reading $reading) {
    $profile = ResourceExpressionHandlers::forNonResourceSubjects();

    expect(fn () => new ResourceAstAnalyzer(new ReflectionClass(InertiaController::class), null, 'dashboard', $profile, reading: $reading))
        ->toThrow(LogicException::class, 'An injected handler profile reads as json_encode() sends; an Inertia reading builds its own.');
})->with([Reading::Inertia, Reading::InertiaFiltered]);

// Ordering pin: StaticCallHandler's last arm claims every StaticCall on a named class, so after it
// ModelFinderHandler is unreachable and `Post::find(1)` reflects as an ordinary static method.
it('tries ModelFinderHandler before StaticCallHandler for Post::find(1)', function () {
    $expr = new StaticCall(new Name(Post::class), 'find', [new Arg(new Int_(1))]);

    expect(inertiaProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'Post | null',
        'optional' => false,
        'modelFqcn' => Post::class,
    ]);
});

// Ordering pin: StaticCallHandler reflects `make()`, and NewResourceHandler reads a `new` of a class no resource, each
// as `unknown`, where Inertia sends the table's toArray(). It throws in the stub package, so no oracle runs here.
it('tries InertiaTableHandler before StaticCallHandler and NewResourceHandler for a table', function () {
    $made = new StaticCall(new Name(PostTable::class), 'make');
    $built = new New_(new Name(PostTable::class));
    $table = [
        'type' => 'TableResource<Post>',
        'optional' => false,
        'modelFqcn' => Post::class,
        'customImports' => ['@inertiaui/table-vue' => ['TableResource']],
    ];

    expect(inertiaProfileAnalyzer()->resolve($made))->toBe($table)
        ->and(inertiaProfileAnalyzer()->resolve($built))->toBe($table)
        ->and(inertiaProfileAnalyzer()->resolve(new MethodCall($made, 'defaultSort', [new Arg(new String_('-id'))])))->toBe($table);
});

// Both claim a static call and a chain on it, but on disjoint roots, a model and a table, so their order is free.
test('ModelFinderHandler and InertiaTableHandler decline each other\'s calls', function () {
    $scope = new AnalysisScope(new ReflectionClass(InertiaController::class));
    $table = new StaticCall(new Name(PostTable::class), 'make');
    $query = new StaticCall(new Name(Post::class), 'find', [new Arg(new Int_(1))]);

    expect(new ModelFinderHandler()->resolve($table, $scope, inertiaProfileAnalyzer()))->toBeNull()
        ->and(new InertiaTableHandler()->resolve($query, $scope, inertiaProfileAnalyzer()))->toBeNull();
});

// Ordering pin: same last-arm problem — StaticCallHandler types ::collection() as the element array,
// which is the nested-resource answer, not the page-prop payload.
it('tries InertiaResourcePropHandler before StaticCallHandler for WarehouseResource::collection()', function () {
    $expr = new StaticCall(new Name(WarehouseResource::class), 'collection', [new Arg(new Variable('rows'))]);

    expect(inertiaProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'AnonymousResourceCollection<WarehouseResource>',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
        'customImports' => ['@tolki/types' => ['AnonymousResourceCollection']],
        'closedToDottedKeys' => 'AnonymousResourceCollection<WarehouseResource>',
    ]);
});

// Ordering pin: NewResourceHandler resolves a collection to its collected element array, so running
// first it would answer `PostResource[]` and the PostCollection interface would never be referenced.
it('tries InertiaResourcePropHandler before NewResourceHandler for new PostCollection()', function () {
    $expr = new New_(new Name(PostCollection::class), [new Arg(new Variable('rows'))]);

    expect(inertiaProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'PostCollection',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
        'closedToDottedKeys' => 'PostCollection',
    ]);
});

// Ordering pin: NewResourceHandler and StaticCallHandler type a single resource as the value a nested key holds, while
// Inertia sends a page prop as its response, inside the wrap key.
it('tries InertiaResourcePropHandler before NewResourceHandler and StaticCallHandler for a single resource', function () {
    $built = new New_(new Name(WarehouseResource::class), [new Arg(new Variable('row'))]);
    $made = new StaticCall(new Name(WarehouseResource::class), 'make', [new Arg(new Variable('row'))]);

    expect(inertiaProfileAnalyzer()->resolve($built))->toBe([
        'type' => '{ data: WarehouseResource }',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
        'closedToDottedKeys' => '{ data: WarehouseResource }',
    ])
        ->and(inertiaProfileAnalyzer()->resolve($made)['type'])->toBe('{ data: WarehouseResource }');
});

// ToResourceHandler after it types these calls as a nested key's value, and the receiver handlers leave them `unknown`,
// where Inertia sends the response.
it('tries InertiaResourcePropHandler before ToResourceHandler and the receiver handlers for a toResource() or toResourceCollection() call', function () {
    $reflection = new ReflectionClass(InertiaController::class);
    $scope = new AnalysisScope($reflection);
    $scope->varModelBindings['warehouse'] = Warehouse::class;
    $analyzer = new ResourceAstAnalyzer($reflection, null, 'dashboard', scope: $scope, reading: Reading::Inertia);
    $paginator = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]);

    expect($analyzer->resolve(new MethodCall(new Variable('warehouse'), 'toResource'))['type'])->toBe('{ data: WarehouseResource }')
        ->and($analyzer->resolve(new NullsafeMethodCall(new Variable('warehouse'), 'toResource'))['type'])->toBe('{ data: WarehouseResource } | null')
        ->and($analyzer->resolve(new MethodCall($paginator, 'toResourceCollection'))['type'])->toBe('PostCollection & ResourcePagination');
});

// Both claim a MethodCall, but by disjoint names, so each declines the other's and their order is free.
test('ModelFinderHandler and InertiaResourcePropHandler decline each other\'s method calls', function () {
    $reflection = new ReflectionClass(InertiaController::class);
    $paginator = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]);
    $collection = new MethodCall($paginator, 'toResourceCollection');

    expect(new ModelFinderHandler()->resolve($collection, new AnalysisScope($reflection), inertiaProfileAnalyzer()))->toBeNull()
        ->and(new InertiaResourcePropHandler()->resolve($paginator, new AnalysisScope($reflection), inertiaProfileAnalyzer()))->toBeNull()
        ->and(new ModelFinderHandler()->resolve($paginator, new AnalysisScope($reflection), inertiaProfileAnalyzer())['type'] ?? null)
        ->toBe('LengthAwarePaginator<Post>');
});

// Inertia calls every callable prop, at any depth, before it encodes it, so a first-class callable types its call.
it('types a first-class-callable prop as the call it stands for', function () {
    $parse = fn (string $source): Expr => new AstParser()->parseSource('<?php '.$source.';')[0]->expr;
    $props = $parse('["meta" => ["stamp" => now(...)]]');
    assert($props instanceof Array_);
    $meta = collect(inertiaProfileAnalyzer()->returnArrayAnalysis($props, topLevel: true)->properties)->firstWhere('name', 'meta');

    expect(inertiaProfileAnalyzer()->resolve($parse('now(...)'))['type'])->toBe('string')
        ->and(inertiaProfileAnalyzer()->resolve($parse('auth()->user(...)'))['type'])->toBe('User | null')
        ->and($meta['type'])->toBe('{ stamp: string }')
        ->and(AnalysisWarnings::all())->toBe([]);
});

// The model-less half of a pair the ordering matrix runs only on the resource profile: on a controller's held
// receivers both handlers claim these calls, and answer them alike, so their order is free here too.
it('RelationCollectionChainHandler and ReceiverMethodCallHandler agree on a call on a held receiver', function (string $php, string $type) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $subject = new ReflectionClass(PostReceiverController::class);
    $engine = new ResourceAstAnalyzer($subject, null, '__construct', reading: Reading::Inertia);

    $chain = new RelationCollectionChainHandler()->resolve($expr, new AnalysisScope($subject), $engine);
    $receiver = new ReceiverMethodCallHandler()->resolve($expr, new AnalysisScope($subject), $engine);

    expect($chain['type'] ?? null)->toBe($type)
        ->and($receiver)->toBe($chain);
})->with([
    ['$this->post->commentsCount()', 'number'],
    ['$this->formatter->title()', 'string'],
    ['$this->checks->toArray()', '{ message: string; route?: string }[]'],
]);
