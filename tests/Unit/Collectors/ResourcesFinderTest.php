<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use Illuminate\Support\Collection;

test('resources collector finds resource classes', function () {
    $collector = resolve(ResourcesCollector::class);

    $resources = $collector->collect();

    expect($resources)
        ->toBeInstanceOf(Collection::class)
        ->toContain('Workbench\App\Http\Resources\PostResource')
        ->toContain('Workbench\App\Http\Resources\UserResource')
        ->toContain('Workbench\App\Http\Resources\CommentResource')
        ->toContain('Workbench\App\Http\Resources\OrderResource');
});

test('resources collector excludes EnumResource', function () {
    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)->not->toContain('AbeTwoThree\LaravelTsPublish\EnumResource');
});

test('resources collector respects included_resources config', function () {
    config()->set('ts-publish.resources.included', [
        'Workbench\App\Http\Resources\PostResource',
    ]);

    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)
        ->toHaveCount(1)
        ->toContain('Workbench\App\Http\Resources\PostResource');
});

test('resources collector respects excluded_resources config', function () {
    config()->set('ts-publish.resources.excluded', [
        'Workbench\App\Http\Resources\PostResource',
    ]);

    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)
        ->not->toContain('Workbench\App\Http\Resources\PostResource')
        ->toContain('Workbench\App\Http\Resources\UserResource');
});

test('resources collector includes ResourceCollection subclasses', function () {
    $resources = resolve(ResourcesCollector::class)->collect();

    expect($resources)
        ->toContain('Workbench\App\Http\Resources\UserCollection')
        ->toContain('Workbench\App\Http\Resources\OrderCollection');
});
