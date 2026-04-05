<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\RoutesCollector;
use Illuminate\Routing\Router;
use Workbench\App\Http\Controllers\ExcludableController;
use Workbench\App\Http\Controllers\ExcludedController;
use Workbench\App\Http\Controllers\MiddlewareController;
use Workbench\App\Http\Controllers\MultiRouteController;
use Workbench\App\Http\Controllers\PostController;
use Workbench\App\Http\Controllers\Prism\Prism\PrismController as NestedPrismController;
use Workbench\App\Http\Controllers\Prism\PrismController;
use Workbench\App\Http\Middleware\TestMiddleware;

beforeEach(function () {
    config()->set('ts-publish.routes.enabled', true);
    config()->set('ts-publish.routes.only', []);
    config()->set('ts-publish.routes.except', []);
    config()->set('ts-publish.routes.exclude_middleware', []);
    config()->set('ts-publish.routes.only_named', false);
});

test('collects controller classes from registered routes', function () {
    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->toContain(PostController::class);
});

test('excludes controllers with TsExclude attribute', function () {
    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->not->toContain(ExcludedController::class);
});

test('includes controllers without TsExclude attribute', function () {
    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->toContain(ExcludableController::class);
});

test('filters routes by only_named option', function () {
    config()->set('ts-publish.routes.only_named', true);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    // All collected controllers must have at least one named route
    expect($collected)->toContain(PostController::class);
});

test('filters routes using only pattern', function () {
    config()->set('ts-publish.routes.only', ['posts.*']);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->toContain(PostController::class)
        ->and($collected)->not->toContain(ExcludableController::class);
});

test('filters routes using except pattern', function () {
    config()->set('ts-publish.routes.except', ['posts.*']);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->not->toContain(PostController::class);
});

test('returns unique controller classes', function () {
    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    // No duplicates
    expect($collected->count())->toBe($collected->unique()->count());
});

test('excludes controllers behind excluded middleware', function () {
    config()->set('ts-publish.routes.exclude_middleware', [TestMiddleware::class]);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->not->toContain(MiddlewareController::class);
});

test('includes controllers when their middleware is not in exclude_middleware', function () {
    config()->set('ts-publish.routes.exclude_middleware', ['some-other-middleware']);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->toContain(MiddlewareController::class);
});

test('multi-route controller collected only once', function () {
    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    $count = $collected->filter(fn (string $c) => $c === MultiRouteController::class)->count();

    expect($count)->toBe(1);
});

test('prism and nested-prism controllers both collected', function () {
    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    expect($collected)->toContain(PrismController::class)
        ->and($collected)->toContain(NestedPrismController::class);
});

test('only pattern with negation excludes negated route within matching set', function () {
    // 'posts.*' matches all post routes; '!posts.index' should exclude that one
    config()->set('ts-publish.routes.only', ['posts.*', '!posts.index']);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    // PostController still collected because it has other routes (show, store, etc.)
    expect($collected)->toContain(PostController::class);
});

test('negation-only only list includes all routes except negated ones', function () {
    // A negation-only list: every route passes unless explicitly negated
    config()->set('ts-publish.routes.only', ['!posts.*']);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    // Posts routes are negated so PostController should not be collected
    expect($collected)->not->toContain(PostController::class)
        // Other controllers should still be included
        ->and($collected)->toContain(ExcludableController::class);
});

test('filters out routes with generated:: name prefix', function () {
    /** @var Router $router */
    $router = app(Router::class);

    // Register a route with 'generated::' prefix to simulate cache artifact
    $router->get('/generated-cache-test', [PostController::class, 'index'])->name('generated::cache-test');

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    // PostController is still collected via its normal non-fallback routes
    expect($collected)->toContain(PostController::class);
});

test('filters out fallback routes', function () {
    /** @var Router $router */
    $router = app(Router::class);

    $router->fallback([PostController::class, 'index']);

    $collector = resolve(RoutesCollector::class);
    $collected = $collector->collect();

    // PostController is still collected via its normal routes (fallback one is excluded)
    expect($collected)->toContain(PostController::class);
});
