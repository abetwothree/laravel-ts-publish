<?php

use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Workbench\App\Models\User;

test('generates model typescript content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelGenerator::class, ['findable' => User::class]);

    expect($generator->content)
        ->toContain('export interface User')
        ->toContain('id: number')
        ->toContain('name: string');
});

test('exposes transformer property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelGenerator::class, ['findable' => User::class]);

    expect($generator->transformer)->toBeInstanceOf(ModelTransformer::class)
        ->and($generator->transformer->modelName)->toBe('User');
});

test('exposes findable property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelGenerator::class, ['findable' => User::class]);

    expect($generator->findable)->toBe(User::class);
});

test('filename delegates to transformer', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(ModelGenerator::class, ['findable' => User::class]);

    expect($generator->filename())->toBe('user');
});
