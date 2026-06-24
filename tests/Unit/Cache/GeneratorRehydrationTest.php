<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Workbench\App\Models\User;

it('rehydrates a model generator from a cached transformer without regenerating', function () {
    $transformer = new ModelTransformer(User::class);

    $generator = ModelGenerator::fromCache(User::class, $transformer, 'cached-user');

    expect($generator)->toBeInstanceOf(ModelGenerator::class)
        ->and($generator->filename())->toBe('cached-user')
        ->and($generator->transformer->modelName)->toBe($transformer->modelName);
});
