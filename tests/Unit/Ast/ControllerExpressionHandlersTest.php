<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ModelFinderHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\NewResourceHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationCollectionChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\StaticCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\PostReceiverController;
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
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;

/** An analyzer over the controller profile with no backing model, as the page analyzer builds it. */
function controllerProfileAnalyzer(): ResourceAstAnalyzer
{
    return new ResourceAstAnalyzer(
        new ReflectionClass(InertiaController::class),
        null,
        'dashboard',
        ControllerExpressionHandlers::make(),
    );
}

it('inserts both controller handlers immediately before StaticCallHandler and changes nothing else', function () {
    $controller = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ControllerExpressionHandlers::make(),
    );

    $generic = array_map(
        fn (ExpressionHandler $handler): string => $handler::class,
        ResourceExpressionHandlers::withoutResourceHandlers(),
    );

    $expected = [];

    foreach ($generic as $class) {
        if ($class === StaticCallHandler::class) {
            $expected[] = ModelFinderHandler::class;
            $expected[] = InertiaResourcePropHandler::class;
        }

        $expected[] = $class;
    }

    expect($controller)->toBe($expected)
        ->and($controller)->toHaveCount(count($generic) + 2);
});

// Ordering pin: StaticCallHandler's last arm claims every StaticCall on a named class, so after it
// ModelFinderHandler is unreachable and `Post::find(1)` reflects as an ordinary static method.
it('tries ModelFinderHandler before StaticCallHandler for Post::find(1)', function () {
    $expr = new StaticCall(new Name(Post::class), 'find', [new Arg(new Int_(1))]);

    expect(controllerProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'Post | null',
        'optional' => false,
        'modelFqcn' => Post::class,
    ]);
});

// Ordering pin: same last-arm problem — StaticCallHandler types ::collection() as the element array,
// which is the nested-resource answer, not the page-prop payload.
it('tries InertiaResourcePropHandler before StaticCallHandler for WarehouseResource::collection()', function () {
    $expr = new StaticCall(new Name(WarehouseResource::class), 'collection', [new Arg(new Variable('rows'))]);

    expect(controllerProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'AnonymousResourceCollection<WarehouseResource>',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
        'customImports' => ['@tolki/types' => ['AnonymousResourceCollection']],
    ]);
});

// Ordering pin: NewResourceHandler resolves a collection to its collected element array, so running
// first it would answer `PostResource[]` and the PostCollection interface would never be referenced.
it('tries InertiaResourcePropHandler before NewResourceHandler for new PostCollection()', function () {
    $expr = new New_(new Name(PostCollection::class), [new Arg(new Variable('rows'))]);

    expect(controllerProfileAnalyzer()->resolve($expr))->toBe([
        'type' => 'PostCollection',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
    ]);
});

// Ordering pin: NewResourceHandler and StaticCallHandler type a single resource as the value a nested key holds, while
// Inertia sends a page prop as its response, inside the wrap key.
it('tries InertiaResourcePropHandler before NewResourceHandler and StaticCallHandler for a single resource', function () {
    $built = new New_(new Name(WarehouseResource::class), [new Arg(new Variable('row'))]);
    $made = new StaticCall(new Name(WarehouseResource::class), 'make', [new Arg(new Variable('row'))]);

    expect(controllerProfileAnalyzer()->resolve($built))->toBe([
        'type' => '{ data: WarehouseResource }',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
    ])
        ->and(controllerProfileAnalyzer()->resolve($made)['type'])->toBe('{ data: WarehouseResource }');
});

// The method-call claimants after it leave these calls `unknown`, where Inertia sends the response.
it('tries InertiaResourcePropHandler before the receiver handlers for a toResource() or toResourceCollection() call', function () {
    $reflection = new ReflectionClass(InertiaController::class);
    $scope = new AnalysisScope($reflection);
    $scope->varModelBindings['warehouse'] = Warehouse::class;
    $analyzer = new ResourceAstAnalyzer($reflection, null, 'dashboard', ControllerExpressionHandlers::make(), $scope);
    $paginator = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]);

    expect($analyzer->resolve(new MethodCall(new Variable('warehouse'), 'toResource'))['type'])->toBe('{ data: WarehouseResource }')
        ->and($analyzer->resolve(new NullsafeMethodCall(new Variable('warehouse'), 'toResource'))['type'])->toBe('{ data: WarehouseResource } | null')
        ->and($analyzer->resolve(new MethodCall($paginator, 'toResourceCollection'))['type'])->toBe('PostCollection & ResourcePagination');
});

// Both claim a MethodCall, but by disjoint names, so each declines the other's and their order is free.
it('ModelFinderHandler and InertiaResourcePropHandler decline each other\'s method calls', function () {
    $reflection = new ReflectionClass(InertiaController::class);
    $paginator = new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]);
    $collection = new MethodCall($paginator, 'toResourceCollection');

    expect(new ModelFinderHandler()->resolve($collection, new AnalysisScope($reflection), controllerProfileAnalyzer()))->toBeNull()
        ->and(new InertiaResourcePropHandler()->resolve($paginator, new AnalysisScope($reflection), controllerProfileAnalyzer()))->toBeNull()
        ->and(new ModelFinderHandler()->resolve($paginator, new AnalysisScope($reflection), controllerProfileAnalyzer())['type'] ?? null)
        ->toBe('LengthAwarePaginator<Post>');
});

// Inertia calls every callable prop, at any depth, before it encodes it, so a first-class callable types its call.
it('types a first-class-callable prop as the call it stands for', function () {
    $parse = fn (string $source): Expr => new AstParser()->parseSource('<?php '.$source.';')[0]->expr;
    $props = $parse('["meta" => ["stamp" => now(...)]]');
    assert($props instanceof Array_);
    $meta = collect(controllerProfileAnalyzer()->returnArrayAnalysis($props, topLevel: true)->properties)->firstWhere('name', 'meta');

    expect(controllerProfileAnalyzer()->resolve($parse('now(...)'))['type'])->toBe('string')
        ->and(controllerProfileAnalyzer()->resolve($parse('auth()->user(...)'))['type'])->toBe('User | null')
        ->and($meta['type'])->toBe('{ stamp: string }')
        ->and(AnalysisWarnings::all())->toBe([]);
});

// The model-less half of a pair the ordering matrix runs only on the resource profile: on a controller's held
// receivers both handlers claim these calls, and answer them alike, so their order is free here too.
it('RelationCollectionChainHandler and ReceiverMethodCallHandler agree on a call on a held receiver', function (string $php, string $type) {
    $expr = new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
    $subject = new ReflectionClass(PostReceiverController::class);
    $engine = new ResourceAstAnalyzer($subject, null, '__construct', ControllerExpressionHandlers::make());

    $chain = new RelationCollectionChainHandler()->resolve($expr, new AnalysisScope($subject), $engine);
    $receiver = new ReceiverMethodCallHandler()->resolve($expr, new AnalysisScope($subject), $engine);

    expect($chain['type'] ?? null)->toBe($type)
        ->and($receiver)->toBe($chain);
})->with([
    ['$this->post->commentsCount()', 'number'],
    ['$this->formatter->title()', 'string'],
    ['$this->checks->toArray()', '{ message: string; route?: string }[]'],
]);
