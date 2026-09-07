<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Workbench\App\Models\User;

it('rehydrates a model generator from a cached transformer without regenerating', function () {
    $transformer = new ModelTransformer(User::class);

    $generator = ModelGenerator::fromCache(User::class, $transformer, 'cached-user');

    expect($generator)->toBeInstanceOf(ModelGenerator::class)
        ->and($generator->filename())->toBe('cached-user')
        ->and($generator->transformer->modelName)->toBe($transformer->modelName);
});

it('rehydrates a model metadata generator from a cached transformer without regenerating', function () {
    $transformer = new ModelMetadataTransformer(User::class);

    $generator = ModelMetadataGenerator::fromCache(User::class, $transformer, 'cached-user_meta');

    expect($generator)->toBeInstanceOf(ModelMetadataGenerator::class)
        ->and($generator->filename())->toBe('cached-user_meta')
        ->and($generator->transformer->modelName)->toBe($transformer->modelName);
});
