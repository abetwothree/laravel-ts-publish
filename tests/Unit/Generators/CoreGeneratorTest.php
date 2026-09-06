<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelMetadataTransformer;
use Workbench\App\Models\User;

test('namespacePath throws a LogicException when the transformer has not set one', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelMetadataGenerator::class, ['findable' => User::class]);

    // Same shape a custom transformer_class that never assigns namespacePath would produce.
    $transformer = (new ReflectionClass(ModelMetadataTransformer::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($generator, 'transformer'))->setValue($generator, $transformer);

    expect(fn () => $generator->namespacePath())
        ->toThrow(LogicException::class, 'must have a core transformer with a namespace path');
});
