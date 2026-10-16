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
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SometimesDataKeyPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\SometimesDataKeyPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubFlatCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\StubGadgetResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Cog;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
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
use Workbench\App\Models\Team;
use Workbench\App\Models\Warehouse;

/** An expression holding `Post::query()->paginate()`, which ModelFinderHandler types as a paginator. */
function paginatorArg(): Arg
{
    return new Arg(new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]));
}

/** An expression holding `Post::query()->{$terminal}(10)`, a simple or cursor paginator. */
function paginatorKindArg(string $terminal): Arg
{
    return new Arg(new MethodCall(new StaticCall(new Name(Post::class), 'query'), $terminal, [new Arg(new Int_(10))]));
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

it('types a wrapping collection over a simple or cursor paginator beside that paginator\'s pagination members', function () {
    $simple = new New_(new Name(PostCollection::class), [paginatorKindArg('simplePaginate')]);
    $cursor = new New_(new Name(PostCollection::class), [paginatorKindArg('cursorPaginate')]);

    expect(inertiaResourcePropResult($simple))->toBe([
        'type' => 'PostCollection & ResourceSimplePagination',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
        'customImports' => ['@tolki/types' => ['ResourceSimplePagination']],
    ])
        ->and(inertiaResourcePropResult($cursor)['type'] ?? null)->toBe('PostCollection & ResourceCursorPagination')
        ->and(inertiaResourcePropResult($cursor)['customImports'] ?? null)->toBe(['@tolki/types' => ['ResourceCursorPagination']]);
});

it('types Resource::collection() and a flat collection over a simple or cursor paginator as that paginator\'s response', function () {
    $anonymous = new StaticCall(new Name(PostResource::class), 'collection', [paginatorKindArg('simplePaginate')]);
    $flat = new New_(new Name(PostFlatCollection::class), [paginatorKindArg('cursorPaginate')]);
    $keyed = new New_(new Name(PreserveKeysFlatCollection::class), [paginatorKindArg('cursorPaginate')]);

    expect(inertiaResourcePropResult($anonymous))->toBe([
        'type' => 'JsonResourceSimplePaginator<PostResource>',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
        'customImports' => ['@tolki/types' => ['JsonResourceSimplePaginator']],
    ])
        ->and(inertiaResourcePropResult($flat)['type'] ?? null)->toBe('JsonResourceCursorPaginator<PostResource>')
        ->and(inertiaResourcePropResult($keyed)['type'] ?? null)
        ->toBe("Omit<JsonResourceCursorPaginator<TeamResource>, 'data'> & { data: Record<string, TeamResource> }");
})->skip(fn () => ! version_compare(app()->version(), '13', '>='));

it('publishes each paginator a payload may hold, and the unpaginated shape beside them when it may hold none', function () {
    $either = new Arg(new Ternary(new Variable('flag'), paginatorKindArg('simplePaginate')->value, paginatorArg()->value));
    $sometimes = new Arg(new Ternary(
        new Variable('flag'),
        new StaticCall(new Name(Post::class), 'all'),
        paginatorKindArg('cursorPaginate')->value,
    ));

    $named = inertiaResourcePropResult(new New_(new Name(PostCollection::class), [$either]));
    $namedSometimes = inertiaResourcePropResult(new New_(new Name(PostCollection::class), [$sometimes]));
    $anonymousSometimes = inertiaResourcePropResult(new StaticCall(new Name(PostResource::class), 'collection', [$sometimes]));

    expect($named['type'] ?? null)->toBe('PostCollection & (ResourceSimplePagination | ResourcePagination)')
        ->and($named['customImports'] ?? null)->toBe(['@tolki/types' => ['ResourceSimplePagination', 'ResourcePagination']])
        ->and($namedSometimes['type'] ?? null)->toBe('PostCollection | (PostCollection & ResourceCursorPagination)')
        ->and($anonymousSometimes['type'] ?? null)->toBe('AnonymousResourceCollection<PostResource> | JsonResourceCursorPaginator<PostResource>')
        ->and($anonymousSometimes['customImports'] ?? null)->toBe(['@tolki/types' => ['AnonymousResourceCollection', 'JsonResourceCursorPaginator']]);
});

it('types toResourceCollection() as the response of the collection Laravel guesses or is given, and declines a receiver it cannot read', function () {
    $guessed = new MethodCall(paginatorKindArg('simplePaginate')->value, 'toResourceCollection');
    $explicit = new MethodCall(paginatorKindArg('cursorPaginate')->value, 'toResourceCollection', [
        new Arg(new ClassConstFetch(new Name(PostResource::class), 'class')),
    ]);
    $nullsafe = new NullsafeMethodCall(new StaticCall(new Name(Post::class), 'all'), 'toResourceCollection');
    $unread = new MethodCall(new Variable('rows'), 'toResourceCollection');

    expect(inertiaResourcePropResult($guessed))->toBe([
        'type' => 'PostCollection & ResourceSimplePagination',
        'optional' => false,
        'resourceFqcn' => PostCollection::class,
        'customImports' => ['@tolki/types' => ['ResourceSimplePagination']],
    ])
        ->and(inertiaResourcePropResult($explicit)['type'] ?? null)->toBe('JsonResourceCursorPaginator<PostResource>')
        ->and(inertiaResourcePropResult($nullsafe)['type'] ?? null)->toBe('PostCollection | null')
        ->and(inertiaResourcePropResult($unread))->toBeNull();
});

// Wrap report: the anonymous collection keeps R's keys whether or not a paginator is beside them.
test('an unpaginated anonymous prop over a key-preserving resource publishes the record', function () {
    $keyed = new StaticCall(new Name(PreserveKeysTeamResource::class), 'collection', [new Arg(new StaticCall(new Name(Team::class), 'all'))]);

    expect(inertiaResourcePropResult($keyed))->toBe([
        'type' => '{ data: Record<string, PreserveKeysTeamResource> }',
        'optional' => false,
        'resourceFqcn' => PreserveKeysTeamResource::class,
    ]);
});

it('nests an anonymous collection\'s items under the live wrap key beside its paginator\'s members', function () {
    $simple = new StaticCall(new Name(PostResource::class), 'collection', [paginatorKindArg('simplePaginate')]);
    $keyed = new StaticCall(new Name(PreserveKeysTeamResource::class), 'collection', [paginatorArg()]);

    JsonResource::wrap('items');

    expect(inertiaResourcePropResult($simple))->toBe([
        'type' => '{ items: PostResource[] } & ResourceSimplePagination',
        'optional' => false,
        'resourceFqcn' => PostResource::class,
        'customImports' => ['@tolki/types' => ['ResourceSimplePagination']],
    ])
        ->and(inertiaResourcePropResult($keyed)['type'] ?? null)
        ->toBe('{ items: Record<string, PreserveKeysTeamResource> } & ResourcePagination');
});

it('types toResource() over a receiver holding several models as the union of their responses', function () {
    $scope = new AnalysisScope(new ReflectionClass(InertiaController::class));
    $scope->varClassBindings['row'] = [Warehouse::class, Post::class];

    expect(inertiaResourcePropResult(new MethodCall(new Variable('row'), 'toResource'), $scope))->toBe([
        'type' => '{ data: WarehouseResource } | { data: PostResource }',
        'optional' => false,
        'embeddedResourceFqcns' => [WarehouseResource::class, PostResource::class],
    ]);
});

it('parenthesises a body that holds its wrap key on some paths beside each paginator\'s members', function () {
    $paginated = new New_(new Name(SometimesDataKeyPostCollection::class), [paginatorArg()]);
    $either = new New_(new Name(SometimesDataKeyPostCollection::class), [
        new Arg(new Ternary(new Variable('flag'), paginatorKindArg('simplePaginate')->value, paginatorArg()->value)),
    ]);

    expect(inertiaResourcePropResult($paginated)['type'] ?? null)
        ->toBe('(SometimesDataKeyPostCollection | { data: SometimesDataKeyPostCollection }) & ResourcePagination')
        ->and(inertiaResourcePropResult($either)['type'] ?? null)
        ->toBe('(SometimesDataKeyPostCollection | { data: SometimesDataKeyPostCollection }) & (ResourceSimplePagination | ResourcePagination)');
});

// A collection given null throws, so a null arm sends no unpaginated response; a `?->` adds its own `| null`.
it('reads a nullable paginator payload\'s kinds without its null arm', function () {
    $null = new ConstFetch(new Name('null'));
    $nullable = new Ternary(new Variable('flag'), paginatorArg()->value, $null);

    expect(inertiaResourcePropResult(new New_(new Name(PostCollection::class), [new Arg($nullable)]))['type'] ?? null)
        ->toBe('PostCollection & ResourcePagination')
        ->and(inertiaResourcePropResult(new StaticCall(new Name(PostResource::class), 'collection', [new Arg($nullable)]))['type'] ?? null)
        ->toBe('JsonResourcePaginator<PostResource>')
        ->and(inertiaResourcePropResult(new MethodCall($nullable, 'toResourceCollection'))['type'] ?? null)
        ->toBe('PostCollection & ResourcePagination')
        ->and(inertiaResourcePropResult(new New_(new Name(PostCollection::class), [new Arg($null)]))['type'] ?? null)
        ->toBe('PostCollection');
});

it('types a $wrap = null collection whose body sends the collected list as its paginator\'s response, and beside its unpaginated self', function () {
    $sometimes = new Arg(new Ternary(new Variable('flag'), new StaticCall(new Name(Post::class), 'all'), paginatorArg()->value));

    expect(inertiaResourcePropResult(new New_(new Name(StubFlatCollection::class), [paginatorArg()])))->toBe([
        'type' => 'JsonResourcePaginator<StubGadgetResource>',
        'optional' => false,
        'resourceFqcn' => StubGadgetResource::class,
        'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
    ])
        ->and(inertiaResourcePropResult(new New_(new Name(StubFlatCollection::class), [$sometimes])))->toBe([
            'type' => 'StubFlatCollection | JsonResourcePaginator<StubGadgetResource>',
            'optional' => false,
            'resourceFqcn' => StubGadgetResource::class,
            'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
            'embeddedResourceFqcns' => [StubFlatCollection::class],
        ]);
});

// Laravel calls `X::collection($items)`, which for a collection or the EnumResource builds no anonymous resource list.
it('declines toResourceCollection() of a collection or the EnumResource, given or named by #[UseResource]', function () {
    $posts = new StaticCall(new Name(Post::class), 'all');
    $given = fn (string $class): MethodCall => new MethodCall($posts, 'toResourceCollection', [new Arg(new ClassConstFetch(new Name($class), 'class'))]);

    expect(inertiaResourcePropResult($given(PostCollection::class)))->toBeNull()
        ->and(inertiaResourcePropResult($given(EnumResource::class)))->toBeNull();
});

it('declines a guessed toResourceCollection() whose #[UseResource] names a collection', function () {
    expect(inertiaResourcePropResult(new MethodCall(new StaticCall(new Name(Cog::class), 'all'), 'toResourceCollection')))->toBeNull();
})->skip(
    ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResource'),
    'UseResource attribute requires Laravel 12.29+',
);
