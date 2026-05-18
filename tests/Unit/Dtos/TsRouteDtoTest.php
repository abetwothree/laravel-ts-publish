<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\TsRouteDto;

test('toArray returns correct structure', function () {
    $dto = new TsRouteDto(
        controllerName: 'PostController',
        filePath: 'app/http/controllers/post-controller',
        fqcn: 'App\Http\Controllers\PostController',
        description: 'Manages blog posts',
        actions: [
            [
                'name' => 'posts.index',
                'url' => null,
                'uri' => '/posts',
                'domain' => null,
                'methods' => ['get'],
                'methodName' => 'index',
                'originalMethodName' => 'index',
                'description' => null,
                'args' => [],
            ],
        ],
    );

    $array = $dto->toArray();

    expect($array)
        ->toHaveKey('controllerName', 'PostController')
        ->toHaveKey('filePath', 'app/http/controllers/post-controller')
        ->toHaveKey('fqcn', 'App\Http\Controllers\PostController')
        ->toHaveKey('description', 'Manages blog posts')
        ->toHaveKey('actions')
        ->and($array['actions'])->toHaveCount(1);
});

test('toJson returns valid JSON string', function () {
    $dto = new TsRouteDto(
        controllerName: 'PostController',
        filePath: 'app/http/controllers/post-controller',
        fqcn: 'App\Http\Controllers\PostController',
        description: null,
        actions: [],
    );

    $json = $dto->toJson();

    expect(json_decode($json, true))
        ->toBeArray()
        ->toHaveKey('controllerName', 'PostController')
        ->toHaveKey('description', null);
});

test('jsonSerialize returns array matching toArray', function () {
    $dto = new TsRouteDto(
        controllerName: 'PostController',
        filePath: 'app/http/controllers/post-controller',
        fqcn: 'App\Http\Controllers\PostController',
        description: null,
        actions: [],
    );

    expect($dto->jsonSerialize())->toBe($dto->toArray());
});
