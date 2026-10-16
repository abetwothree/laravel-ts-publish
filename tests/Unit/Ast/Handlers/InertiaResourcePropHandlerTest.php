<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\DataKeyPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ItemsKeyPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi\PagePropApiResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SometimesDataKeyPostResource;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Workbench\App\Enums\Role;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Resources\OrderCollection;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Http\Resources\PreserveKeysTeamResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\Warehouse;

/** An expression holding `Post::query()->paginate()`, which ModelFinderHandler types as a paginator. */
function paginatorArg(): Arg
{
    return new Arg(new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]));
}

/** Resolve one expression through the handler under test, over the controller profile and the given scope. */
function inertiaResourcePropResult(Expr $expr, ?AnalysisScope $scope = null): ?array
{
    $reflection = new ReflectionClass(InertiaController::class);
    $scope ??= new AnalysisScope($reflection);

    return new InertiaResourcePropHandler()->resolve(
        $expr,
        $scope,
        new ResourceAstAnalyzer($reflection, null, 'dashboard', ControllerExpressionHandlers::make(), $scope),
    );
}

afterEach(function () {
    JsonResource::flushState();
});

it('types a wrapping collection over a paginator beside the pagination members', function () {
    $expr = new New_(new Name(PostCollection::class), [paginatorArg()]);

    expect(inertiaResourcePropResult($expr))->toBe([
        'type' => 'PostCollection & ResourcePagination',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
        'customImports' => ['@tolki/types' => ['ResourcePagination']],
    ]);
});

it('types a flat collection over a paginator as the paginator over its singular resource', function () {
    $expr = new New_(new Name(PostFlatCollection::class), [paginatorArg()]);

    expect(inertiaResourcePropResult($expr))->toBe([
        'type' => 'JsonResourcePaginator<PostResource>',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
        'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
    ]);
})->skip(fn () => ! version_compare(app()->version(), '13', '>='));

it('keys the paginator data when a flat collection preserves its source keys', function () {
    $expr = new New_(new Name(PreserveKeysFlatCollection::class), [paginatorArg()]);

    expect(inertiaResourcePropResult($expr))->toBe([
        'type' => "Omit<JsonResourcePaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> }",
        'optional' => false,
        'resourceFqcn' => TeamResource::class,
        'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
    ]);
});

it('types a collection over a plain collection as itself', function () {
    $expr = new New_(new Name(PostCollection::class), [new Arg(new Variable('rows'))]);

    expect(inertiaResourcePropResult($expr))->toBe([
        'type' => 'PostCollection',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
    ]);
});

it('types Resource::collection() as an anonymous collection, or a paginator when it wraps one', function () {
    $plain = new StaticCall(new Name(PostResource::class), 'collection', [new Arg(new Variable('rows'))]);
    $paginated = new StaticCall(new Name(PostResource::class), 'collection', [paginatorArg()]);
    $keyed = new StaticCall(new Name(PreserveKeysTeamResource::class), 'collection', [paginatorArg()]);

    expect(inertiaResourcePropResult($plain)['type'])->toBe('AnonymousResourceCollection<PostResource>')
        ->and(inertiaResourcePropResult($paginated)['type'])->toBe('JsonResourcePaginator<PostResource>')
        ->and(inertiaResourcePropResult($keyed)['type'])
        ->toBe("Omit<JsonResourcePaginator<PreserveKeysTeamResource>, 'data'> & { data: Record<string, PreserveKeysTeamResource> }");
});

it('types a single resource as its response inside the live wrap key, bare without one', function () {
    $built = new New_(new Name(WarehouseResource::class), [new Arg(new Variable('warehouse'))]);
    $made = new StaticCall(new Name(WarehouseResource::class), 'make', [new Arg(new Variable('warehouse'))]);
    $wrapped = inertiaResourcePropResult($built);

    JsonResource::wrap('items');
    $itemsKey = inertiaResourcePropResult($made)['type'] ?? null;
    JsonResource::withoutWrapping();

    expect($wrapped)->toBe([
        'type' => '{ data: WarehouseResource }',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
    ])
        ->and($itemsKey)->toBe('{ items: WarehouseResource }')
        ->and(inertiaResourcePropResult($built)['type'] ?? null)->toBe('WarehouseResource')
        ->and(inertiaResourcePropResult($made)['type'] ?? null)->toBe('WarehouseResource');
});

it('types toResource() as the response of the resource Laravel guesses or is given', function () {
    $scope = new AnalysisScope(new ReflectionClass(InertiaController::class));
    $scope->varModelBindings['warehouse'] = Warehouse::class;
    $guessed = new MethodCall(new Variable('warehouse'), 'toResource');
    $nullsafe = new NullsafeMethodCall(new Variable('warehouse'), 'toResource');
    $named = new MethodCall(new Variable('row'), 'toResource', [new Arg(new ClassConstFetch(new Name(PostResource::class), 'class'))]);

    expect(inertiaResourcePropResult($guessed, $scope))->toBe([
        'type' => '{ data: WarehouseResource }',
        'optional' => false,
        'resourceFqcn' => WarehouseResource::class,
    ])
        ->and(inertiaResourcePropResult($nullsafe, $scope)['type'] ?? null)->toBe('{ data: WarehouseResource } | null')
        ->and(inertiaResourcePropResult($named)['type'] ?? null)->toBe('{ data: PostResource }')
        ->and(inertiaResourcePropResult(new MethodCall(new Variable('row'), 'toResource')))->toBeNull()
        ->and(inertiaResourcePropResult(new MethodCall(new Variable('warehouse'), 'toResourceCollection'), $scope))->toBeNull();
});

// Laravel's ResourceResponse nests a body only when it lacks the wrap key, so a key sent on some paths sends both.
it('sends a body that holds its wrap key as it stands, and one that holds it on some paths both ways', function () {
    $held = new New_(new Name(DataKeyPostResource::class), [new Arg(new Variable('post'))]);
    $sometimes = new New_(new Name(SometimesDataKeyPostResource::class), [new Arg(new Variable('post'))]);

    expect(inertiaResourcePropResult($held)['type'] ?? null)->toBe('DataKeyPostResource')
        ->and(inertiaResourcePropResult($sometimes)['type'] ?? null)
        ->toBe('SometimesDataKeyPostResource | { data: SometimesDataKeyPostResource }');
});

it('nests a collection body that lacks its wrap key under it, beside a paginator\'s members', function () {
    $plain = new New_(new Name(ItemsKeyPostCollection::class), [new Arg(new Variable('rows'))]);
    $made = new StaticCall(new Name(ItemsKeyPostCollection::class), 'make', [new Arg(new Variable('rows'))]);
    $paginated = new New_(new Name(ItemsKeyPostCollection::class), [paginatorArg()]);
    $held = new New_(new Name(OrderCollection::class), [paginatorArg()]);
    $wrapped = [inertiaResourcePropResult($plain), inertiaResourcePropResult($made)['type'] ?? null];

    JsonResource::withoutWrapping();

    expect($wrapped)->toBe([
        [
            'type' => '{ data: ItemsKeyPostCollection }',
            'optional' => false,
            'resourceFqcn' => ItemsKeyPostCollection::class,
        ],
        '{ data: ItemsKeyPostCollection }',
    ])
        ->and(inertiaResourcePropResult($plain)['type'] ?? null)->toBe('ItemsKeyPostCollection')
        ->and(inertiaResourcePropResult($paginated)['type'] ?? null)->toBe('{ data: ItemsKeyPostCollection } & ResourcePagination');

    JsonResource::flushState();

    expect(inertiaResourcePropResult($paginated)['type'] ?? null)->toBe('{ data: ItemsKeyPostCollection } & ResourcePagination')
        ->and(inertiaResourcePropResult($held)['type'] ?? null)->toBe('OrderCollection & ResourcePagination');
});

it('types EnumResource::collection() as the enum list inside the anonymous collection\'s wrap key', function () {
    $cases = new StaticCall(new Name(EnumResource::class), 'collection', [new Arg(new StaticCall(new Name(Role::class), 'cases'))]);
    $wrapped = inertiaResourcePropResult($cases);

    JsonResource::withoutWrapping();

    expect($wrapped)->toBe(['type' => '{ data: RoleType[] }', 'optional' => false, 'enumFqcn' => Role::class])
        ->and(inertiaResourcePropResult($cases)['type'] ?? null)->toBe('RoleType[]');
});

it('declines EnumResource::make(), a static call that builds no resource, and an enum list it cannot name', function () {
    $enum = new StaticCall(new Name(EnumResource::class), 'make', [new Arg(new Variable('role'))]);
    $enumCollection = new StaticCall(new Name(EnumResource::class), 'collection', [new Arg(new Variable('roles'))]);
    $finder = new StaticCall(new Name(Post::class), 'make', [new Arg(new Variable('attributes'))]);

    expect(inertiaResourcePropResult($enum))->toBeNull()
        ->and(inertiaResourcePropResult($enumCollection))->toBeNull()
        ->and(inertiaResourcePropResult($finder))->toBeNull();
});

it('leaves a JSON:API resource, whose response is a document, to the value its body publishes', function () {
    $built = new New_(new Name(PagePropApiResource::class), [new Arg(new Variable('post'))]);
    $made = new StaticCall(new Name(PagePropApiResource::class), 'make', [new Arg(new Variable('post'))]);
    $explicit = new MethodCall(new Variable('post'), 'toResource', [new Arg(new ClassConstFetch(new Name(PagePropApiResource::class), 'class'))]);

    expect(inertiaResourcePropResult($built))->toBeNull()
        ->and(inertiaResourcePropResult($made))->toBeNull()
        ->and(inertiaResourcePropResult($explicit))->toBeNull();
})->skip(fn () => ! class_exists('Illuminate\Http\Resources\JsonApi\JsonApiResource'));

it('reads the wrapped paginator by the constructor and collection() parameter name', function () {
    $named = new New_(new Name(PostCollection::class), [new Arg(paginatorArg()->value, name: new Identifier('resource'))]);
    $collection = new StaticCall(new Name(PostResource::class), 'collection', [new Arg(paginatorArg()->value, name: new Identifier('resource'))]);

    expect(inertiaResourcePropResult($named)['type'])->toBe('PostCollection & ResourcePagination')
        ->and(inertiaResourcePropResult($collection)['type'])->toBe('JsonResourcePaginator<PostResource>');
});
