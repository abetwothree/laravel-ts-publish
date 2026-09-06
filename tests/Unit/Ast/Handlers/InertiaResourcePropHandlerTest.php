<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InertiaResourcePropHandler;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Workbench\App\Http\Controllers\InertiaController;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PreserveKeysFlatCollection;
use Workbench\App\Http\Resources\PreserveKeysTeamResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Models\Post;

/** An expression holding `Post::query()->paginate()`, which ModelFinderHandler types as a paginator. */
function paginatorArg(): Arg
{
    return new Arg(new MethodCall(new StaticCall(new Name(Post::class), 'query'), 'paginate', [new Arg(new Int_(10))]));
}

/** Resolve one expression through the handler under test, over the controller profile. */
function inertiaResourcePropResult(Expr $expr): ?array
{
    $reflection = new ReflectionClass(InertiaController::class);

    return new InertiaResourcePropHandler()->resolve(
        $expr,
        new AnalysisScope($reflection),
        new ResourceAstAnalyzer($reflection, null, 'dashboard', ControllerExpressionHandlers::make()),
    );
}

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

it('declines a singular resource, an enum resource, and a non-collection static call', function () {
    $singular = new New_(new Name(PostResource::class), [new Arg(new Variable('post'))]);
    $enumCollection = new StaticCall(new Name(EnumResource::class), 'collection', [new Arg(new Variable('roles'))]);
    $make = new StaticCall(new Name(PostResource::class), 'make', [new Arg(new Variable('post'))]);

    expect(inertiaResourcePropResult($singular))->toBeNull()
        ->and(inertiaResourcePropResult($enumCollection))->toBeNull()
        ->and(inertiaResourcePropResult($make))->toBeNull();
});

it('reads the wrapped paginator by the constructor and collection() parameter name', function () {
    $named = new New_(new Name(PostCollection::class), [new Arg(paginatorArg()->value, name: new Identifier('resource'))]);
    $collection = new StaticCall(new Name(PostResource::class), 'collection', [new Arg(paginatorArg()->value, name: new Identifier('resource'))]);

    expect(inertiaResourcePropResult($named)['type'])->toBe('PostCollection & ResourcePagination')
        ->and(inertiaResourcePropResult($collection)['type'])->toBe('JsonResourcePaginator<PostResource>');
});
