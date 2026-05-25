<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\Inertia\ControllerPaginatorAnalyzer;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\ControllerWithCompact;
use Workbench\App\Http\Controllers\InertiaCollectionsController;
use Workbench\App\Http\Controllers\InertiaNamedCollectionsController;
use Workbench\App\Http\Controllers\InertiaSingleResourceController;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Post;

// ─── edge cases ───────────────────────────────────────────────────

test('returns empty array for non-existent controller class', function () {
    $analyzer = new ControllerPaginatorAnalyzer('NonExistent\\Controller', 'index');

    expect($analyzer->analyze())->toBeEmpty();
});

test('returns empty array for non-existent method name', function () {
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'nonExistentMethod');

    expect($analyzer->analyze())->toBeEmpty();
});

// ─── paginate() ───────────────────────────────────────────────────

test('infers model FQCN from Model::chain()->paginate()', function () {
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'lengthAware');

    expect($analyzer->analyze())->toBe(['posts' => Post::class]);
});

// ─── simplePaginate() ────────────────────────────────────────────

test('infers model FQCN from Model::chain()->simplePaginate()', function () {
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'simple');

    expect($analyzer->analyze())->toBe(['posts' => Post::class]);
});

// ─── cursorPaginate() ────────────────────────────────────────────

test('infers model FQCN from Model::chain()->cursorPaginate()', function () {
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'cursor');

    expect($analyzer->analyze())->toBe(['posts' => Post::class]);
});

// ─── non-variable prop values ─────────────────────────────────────

test('returns empty when paginator result is wrapped in a resource constructor', function () {
    // resource(): $posts = Warehouse::paginate(25) but prop is new WarehouseResource($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'resource');

    expect($analyzer->analyze())->toBeEmpty();
});

test('infers resource FQCN from Resource::collection() prop value', function () {
    // resourceAnonymous(): prop is PostResource::collection($posts) → PostResource::class
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'resourceAnonymous');

    expect($analyzer->analyze())->toBe(['posts' => PostResource::class]);
});

test('returns empty when prop value is a resource object constructor', function () {
    // namedCollection(): $posts = Post::all() (not a paginator) and prop is new PostCollection($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'namedCollection');

    expect($analyzer->analyze())->toBeEmpty();
});

// ─── compact() form ───────────────────────────────────────────────

test('infers model FQCN when props are passed via compact()', function () {
    $analyzer = new ControllerPaginatorAnalyzer(ControllerWithCompact::class, 'index');

    expect($analyzer->analyze())->toBe(['posts' => Post::class]);
});

// ─── analyzePaginatedResourceProps() ──────────────────────────────

test('analyzePaginatedResourceProps returns resource FQCN for new Resource($paginatedVar)', function () {
    // resource(): $posts = Warehouse::paginate(25), prop is new WarehouseResource($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'resource');

    expect($analyzer->analyzePaginatedResourceProps())->toBe(['posts' => WarehouseResource::class]);
});

test('analyzePaginatedResourceProps returns empty when variable is not paginated', function () {
    // resourceGet(): $posts = Warehouse::get(), prop is new WarehouseResource($posts) — not paginated
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'resourceGet');

    expect($analyzer->analyzePaginatedResourceProps())->toBeEmpty();
});

test('analyzePaginatedResourceProps returns empty when variable is from all() not paginate()', function () {
    // namedCollection(): $posts = Post::all(), prop is new PostCollection($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'namedCollection');

    expect($analyzer->analyzePaginatedResourceProps())->toBeEmpty();
});

test('analyzePaginatedResourceProps returns named collection FQCN for paginated new Resource($var)', function () {
    // namedCollectionPaginated(): $posts = Post::paginate(25), prop is new PostCollection($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'namedCollectionPaginated');

    expect($analyzer->analyzePaginatedResourceProps())->toBe(['posts' => PostCollection::class]);
});

test('analyzePaginatedResourceProps returns flat collection FQCN for paginated new FlatCollection($var)', function () {
    // flatCollectionPaginated(): $posts = Post::paginate(25), prop is new PostFlatCollection($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaCollectionsController::class, 'flatCollectionPaginated');

    expect($analyzer->analyzePaginatedResourceProps())->toBe(['posts' => PostFlatCollection::class]);
});

test('analyzePaginatedResourceProps returns empty for non-existent controller', function () {
    $analyzer = new ControllerPaginatorAnalyzer('NonExistent\\Controller', 'index');

    expect($analyzer->analyzePaginatedResourceProps())->toBeEmpty();
});

// ─── analyzePaginatedStaticCollectionProps() ───────────────────────

test('analyzePaginatedStaticCollectionProps returns resource FQCN for paginated Resource::collection($var)', function () {
    // resourcePaginatedCollection(): $warehouses = Warehouse::paginate(25), prop is WarehouseResource::collection($warehouses)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaSingleResourceController::class, 'resourcePaginatedCollection');

    expect($analyzer->analyzePaginatedStaticCollectionProps())->toBe(['warehouses' => WarehouseResource::class]);
});

test('analyzePaginatedStaticCollectionProps returns empty when collection argument is not paginated', function () {
    // resourceAnonymousCollection(): $warehouseGet = Warehouse::get(), prop is WarehouseResource::collection($warehouseGet) — not paginated
    $analyzer = new ControllerPaginatorAnalyzer(InertiaSingleResourceController::class, 'resourceAnonymousCollection');

    expect($analyzer->analyzePaginatedStaticCollectionProps())->toBeEmpty();
});

test('analyzePaginatedStaticCollectionProps returns resource FQCN for paginated PostResource::collection($var)', function () {
    // resourceAnonymousPaginated(): $posts = Post::paginate(25), prop is PostResource::collection($posts)
    $analyzer = new ControllerPaginatorAnalyzer(InertiaNamedCollectionsController::class, 'resourceAnonymousPaginated');

    expect($analyzer->analyzePaginatedStaticCollectionProps())->toBe(['posts' => PostResource::class]);
});

test('analyzePaginatedStaticCollectionProps returns empty when collection argument is not a paginated variable', function () {
    // resourceAnonymous(): $posts = Post::get(), prop is PostResource::collection($posts) — not paginated
    $analyzer = new ControllerPaginatorAnalyzer(InertiaNamedCollectionsController::class, 'resourceAnonymous');

    expect($analyzer->analyzePaginatedStaticCollectionProps())->toBeEmpty();
});

test('analyzePaginatedStaticCollectionProps returns empty for non-existent controller', function () {
    $analyzer = new ControllerPaginatorAnalyzer('NonExistent\\Controller', 'index');

    expect($analyzer->analyzePaginatedStaticCollectionProps())->toBeEmpty();
});
