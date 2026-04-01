<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\EnumTransformer;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

test('generates enum typescript content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->content)
        ->toContain('export const Status')
        ->toContain('Draft')
        ->toContain('Published');
});

test('exposes transformer property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->transformer)->toBeInstanceOf(EnumTransformer::class)
        ->and($generator->transformer->enumName)->toBe('Status');
});

test('exposes findable property', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->findable)->toBe(Status::class);
});

test('filename delegates to transformer', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Status::class]);

    expect($generator->filename())->toBe('status');
});

test('generates unit enum content', function () {
    config()->set('ts-publish.output_to_files', false);

    $generator = resolve(EnumGenerator::class, ['findable' => Role::class]);

    expect($generator->content)
        ->toContain('export const Role')
        ->toContain("'Admin'")
        ->toContain("'User'");
});
