<?php

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Models\User;

test('writes model content from transformer', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface User')
        ->toContain('id: number')
        ->toContain('name: string')
        ->toContain('email: string');
});

test('writes model file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('ensureDirectoryExists')->once();
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'user.ts') && str_contains($content, 'export interface User');
        });

    $writer = new ModelWriter($filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', true);

    $writer->write($transformer);
});

test('does not write model file to disk when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('ensureDirectoryExists');
    $filesystem->shouldNotReceive('put');

    $writer = new ModelWriter($filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes model relations interfaces', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface UserRelations');
});

test('writes model mutators interface', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)->toContain('export interface UserMutators');
});

test('uses import type syntax when use_type_imports is enabled', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.use_type_imports', true);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('import type {')
        ->not->toMatch('/^import \{/m');
});

test('uses regular import syntax when use_type_imports is disabled', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.use_type_imports', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('import {')
        ->not->toContain('import type {');
});
